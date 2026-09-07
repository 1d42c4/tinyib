<?php

declare(strict_types=1);

namespace TinyIB;

final class Application
{
    use BoardOperations;
    use Rendering;
    use Storage;

    private array $account = [];
    private bool $loggedin = false;
    private bool $isadmin = false;
    private string $returnlink = 'imgboard.php';

    public function __construct(
        private readonly Config $config,
        private readonly Database $database,
        private readonly Request $request,
    ) {}

    private function fancyDie(string $message, int $go_back = 1, int $status = 400): never
    {
        throw new BoardMessage($message, $go_back, $status);
    }

    public function run(): void
    {
        $this->initializeAccounts();
        [$this->account, $this->loggedin, $this->isadmin] = $this->manageCheckLogIn(false);

        if (!$this->loggedin) {
            $this->checkBanned();
        }

        $redirect = true;
        // Check if the request is to make a post
        if (!isset($this->request->query['delete']) && !isset($this->request->query['manage']) && (isset($this->request->form['name']) || isset($this->request->form['email']) || isset($this->request->form['subject']) || isset($this->request->form['message']) || isset($this->request->form['file']) || isset($this->request->form['embed']) || isset($this->request->form['password']))) {
            $lock = $this->lockDatabase();
            foreach (['name', 'email', 'subject', 'message', 'password', 'embed'] as $field) {
                if (isset($this->request->form[$field]) && !is_string($this->request->form[$field])) {
                    $this->fancyDie('Invalid post field.');
                }
                $this->request->form[$field] ??= '';
            }

            $staffpost = $this->isStaffPost();
            $capcode = '';
            if (!$staffpost) {
                $this->checkMessageSize();
            }

            $post = $this->newPost($this->setParent());

            if (!$this->loggedin) {
                $this->checkCaptcha($post['parent'] == 0 ? $this->config->captcha : $this->config->replycaptcha);
                $this->checkFlood();
            }

            if (!$this->loggedin) {
                if ($post['parent'] == 0 && $this->config->disallowthreads != '') {
                    $this->fancyDie($this->config->disallowthreads);
                } elseif ($post['parent'] != 0 && $this->config->disallowreplies != '') {
                    $this->fancyDie($this->config->disallowreplies);
                }
            }

            $hide_fields = $post['parent'] == 0 ? $this->config->hidefieldsop : $this->config->hidefields;

            if ($post['parent'] != 0 && !$this->loggedin) {
                $parent = $this->postByID($post['parent']);
                if (!isset($parent['locked'])) {
                    $this->fancyDie('Invalid parent thread ID supplied, unable to create post.');
                } elseif ($parent['locked'] == 1) {
                    $this->fancyDie('Replies are not allowed to locked threads.');
                }
            }

            if ($post['name'] == '' && $post['tripcode'] == '') {

                $post['name'] = $this->config->anonymous[array_rand($this->config->anonymous)];
            }

            $post['ip'] = $this->remoteAddress();

            $spoiler = $this->config->spoilerimage && isset($this->request->form['spoiler']);

            if ($staffpost || !in_array('name', $hide_fields)) {
                [$post['name'], $post['tripcode']] = $this->nameAndTripcode($this->request->form['name']);
                if ($this->config->maxname > 0) {
                    $post['name'] = $this->substring($post['name'], 0, $this->config->maxname);
                }
                $post['name'] = $this->cleanString($post['name']);
            }
            if ($staffpost || !in_array('email', $hide_fields)) {
                $post['email'] = $this->request->form['email'];
                if ($this->config->maxemail > 0) {
                    $post['email'] = $this->substring($post['email'], 0, $this->config->maxemail);
                }
                $post['email'] = $this->cleanString(str_replace('"', '&quot;', $post['email']));
            }
            if ($staffpost) {
                $capcode = ($this->isadmin) ? ' <span style="color: ' . $this->config->capcodes[0][1] . ' ;">## ' . $this->config->capcodes[0][0] . '</span>' : ' <span style="color: ' . $this->config->capcodes[1][1] . ';">## ' . $this->config->capcodes[1][0] . '</span>';
            }
            if ($staffpost || !in_array('subject', $hide_fields)) {
                $post['subject'] = $this->request->form['subject'];
                if ($this->config->maxsubject > 0) {
                    $post['subject'] = $this->substring($post['subject'], 0, $this->config->maxsubject);
                }
                $post['subject'] = $this->cleanString($post['subject']);
            }
            if ($staffpost || !in_array('message', $hide_fields)) {
                $post['message'] = $this->request->form['message'];
                if ($staffpost && isset($this->request->form['raw'])) {
                    // Treat message as raw HTML
                } else {
                    if ($this->config->wordbreak > 0) {
                        $post['message'] = preg_replace('/([^\s]{' . $this->config->wordbreak . '})(?=[^\s])/u', '$1' . '@!@TINYIB_WORDBREAK@!@', $post['message']);
                    }
                    $post['message'] = str_replace("\n", '<br>', $this->makeLinksClickable($this->colorQuote($this->postLink($this->cleanString(rtrim($post['message']))))));

                    if ($this->config->spoilertext) {
                        $post['message'] = preg_replace('/&lt;s&gt;(.*?)&lt;\/s&gt;/i', '<span class="spoiler">$1</span>', $post['message']);
                        $post['message'] = preg_replace('/&lt;spoiler&gt;(.*?)&lt;\/spoiler&gt;/i', '<span class="spoiler">$1</span>', $post['message']);
                        $post['message'] = preg_replace('/&lt;spoilers&gt;(.*?)&lt;\/spoilers&gt;/i', '<span class="spoiler">$1</span>', $post['message']);
                    }

                    if ($this->config->wordbreak > 0) {
                        $post['message'] = $this->finishWordBreak($post['message']);
                    }
                }
            }
            if ($staffpost || !in_array('password', $hide_fields)) {
                $post['password'] = ($this->request->form['password'] !== '') ? Passwords::hash($this->request->form['password']) : '';
            }

            $hide_post = false;
            $report_post = false;
            foreach ([$post['name'], $post['email'], $post['subject'], $post['message']] as $field) {
                $keyword = $this->checkKeywords($field);
                if (empty($keyword)) {
                    continue;
                }

                $expire = -1;
                switch ($keyword['action']) {
                    case 'report':
                        $report_post = true;
                        break;
                    case 'hide':
                        $hide_post = true;
                        break;
                    case 'delete':
                        $this->fancyDie('Your post contains a blocked keyword.');
                        // no break
                    case 'ban0':
                        $expire = 0;
                        break;
                    case 'ban1h':
                        $expire = 3600;
                        break;
                    case 'ban1d':
                        $expire = 86400;
                        break;
                    case 'ban2d':
                        $expire = 172800;
                        break;
                    case 'ban1w':
                        $expire = 604800;
                        break;
                    case 'ban2w':
                        $expire = 1209600;
                        break;
                    case 'ban1m':
                        $expire = 2592000;
                        break;
                }
                if ($expire >= 0) {
                    $ban = [];
                    $ban['ip'] = $post['ip'];
                    $ban['expire'] = $expire > 0 ? (time() + $expire) : 0;
                    $ban['reason'] = 'Keyword' . ': ' . $keyword['text'];
                    $this->insertBan($ban);

                    if ($ban['expire'] > 0) {
                        $bannedText = sprintf('Your IP address (%1$s) is banned until %2$s.', $this->remoteAddress(), $this->formatDate($ban['expire']));
                    } else {
                        $bannedText = sprintf('Your IP address (%s) is permanently banned.', $this->remoteAddress());
                    }
                    {
                        $bannedText .= '<br>' . 'Reason' . ': ' . $ban['reason'];
                    }
                    $this->fancyDie($bannedText);
                }
                break;
            }

            $post['nameblock'] = $this->nameBlock($post['name'], $post['tripcode'], $post['email'], time(), $capcode);

            if (isset($this->request->form['embed']) && trim($this->request->form['embed']) != '' && ($staffpost || !in_array('embed', $hide_fields))) {
                if (isset($this->request->files['file']) && $this->request->files['file']['name'] != '') {
                    $this->fancyDie('Embedding a URL and uploading a file at the same time is not supported.');
                }

                $post = $this->attachRemote($post, trim($this->request->form['embed']), $spoiler);
            } elseif (isset($this->request->files['file']) && $this->request->files['file']['name'] != '' && ($staffpost || !in_array('file', $hide_fields))) {
                $this->validateFileUpload();

                $post = $this->attachFile($post, $this->request->files['file']['tmp_name'], $this->request->files['file']['name'], true, $spoiler);
            }

            if ($post['file'] == '') { // No file uploaded
                $file_ok = !empty($this->config->uploads) && ($staffpost || !in_array('file', $hide_fields));
                $embed_ok = (!empty($this->config->embeds) || $this->config->uploadviaurl) && ($staffpost || !in_array('embed', $hide_fields));
                $allowed = '';
                if ($file_ok && $embed_ok) {
                    $allowed = 'upload a file or embed a URL';
                } elseif ($file_ok) {
                    $allowed = 'upload a file';
                } elseif ($embed_ok) {
                    $allowed = 'embed a URL';
                }
                if ($post['parent'] == 0 && $allowed != '' && !$this->config->nofileok) {
                    $this->fancyDie(sprintf('Please %s to start a new thread.', $allowed));
                }
                if (!$staffpost && str_replace('<br>', '', $post['message']) == '') {
                    $message_ok = !in_array('message', $hide_fields);
                    if ($message_ok) {
                        if ($allowed != '') {
                            $this->fancyDie(sprintf('Please enter a message and/or %s.', $allowed));
                        }
                        $this->fancyDie('Please enter a message.');
                    }
                    $this->fancyDie(sprintf('Please %s.', $allowed));
                }
            }

            if (!$this->loggedin && (($post['file'] != '' && $this->config->reqmod == 'files') || $this->config->reqmod == 'all')) {
                $post['moderated'] = 0;
                echo sprintf('Your %s will be shown <b>once it has been approved</b>.', $post['parent'] == 0 ? 'thread' : 'post') . '<br>';
                $slow_redirect = true;
            }

            $post['id'] = $this->insertPost($post);

            if ($report_post) {
                $report = ['ip' => $post['ip'], 'post' => $post['id']];
                $this->insertReport($report);
                $this->checkAutoHide($post);
            }

            if ($hide_post) {
                $this->approvePostByID($post['id'], 0);
            }

            if ($post['moderated'] === 1) {
                if ($this->config->alwaysnoko || strtolower($post['email']) == 'noko') {
                    $redirect = 'res/' . ($post['parent'] == 0 ? $post['id'] : $post['parent']) . '.html#' . $post['id'];
                }

                $this->trimThreads();

                echo 'Updating thread...' . '<br>';
                if ($post['parent'] != 0) {
                    $this->rebuildThread($post['parent']);

                    if (strtolower($post['email']) != 'sage') {
                        if ($this->config->maxreplies == 0 || $this->numRepliesToThreadByID($post['parent']) <= $this->config->maxreplies) {
                            $this->bumpThreadByID($post['parent']);
                        }
                    }
                } else {
                    $this->rebuildThread($post['id']);
                }

                echo 'Updating index...' . '<br>';
                $this->rebuildIndexes();
            }

            if ($staffpost) {
                $this->manageLogAction('Created staff post' . ' ' . $this->postLink('&gt;&gt;' . $post['id']));
            }
            // Check if the request is to preview a post
        } elseif (isset($this->request->query['preview']) && !isset($this->request->query['manage'])) {
            $post = $this->postByID($this->request->queryInt('preview'));
            if (empty($post)) {
                die('This post has been deleted');
            } elseif ($post['moderated'] == 0 && !$this->isadmin) {
                die('This post requires moderation before it can be displayed');
            }

            $html = $this->buildPost($post, isset($this->request->query['res']), true);
            if (isset($this->request->query['res'])) {
                $html = $this->fixLinksInRes($html);
            }

            echo $html;
            die();
            // Check if the request is to auto-refresh a thread
        } elseif (isset($this->request->query['posts']) && !isset($this->request->query['manage'])) {
            if ($this->config->autorefresh <= 0) {
                $this->fancyDie('Automatic refreshing is disabled.');
            }

            $thread_id = $this->request->queryInt('posts');
            $new_since = $this->request->queryInt('since');
            if ($thread_id <= 0 || $new_since < 0) {
                $this->fancyDie('');
            }

            $json_posts = [];
            $posts = $this->postsInThreadByID($thread_id);
            if ($new_since > 0) {
                foreach ($posts as $i => $post) {
                    if ($post['id'] <= $new_since) {
                        continue;
                    }
                    $json_posts[$post['id']] = $this->fixLinksInRes($this->buildPost($post, true));
                }
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($json_posts, JSON_THROW_ON_ERROR);
            die();
            // Check if the request is to report a post
        } elseif (isset($this->request->query['report']) && !isset($this->request->query['manage'])) {
            $lock = $this->lockDatabase();

            if (!$this->config->report) {
                $this->fancyDie('Reporting is disabled.');
            }

            $post = $this->postByID($this->request->queryInt('report'));
            if (!$post) {
                $this->fancyDie('Sorry, an invalid post identifier was sent. Please go back, refresh the page, and try again.');
            }

            if ($post['moderated'] == 2) {
                $this->fancyDie('Moderators have determined that post does not break any rules.');
            }

            $report = $this->reportByIP($post['id'], $this->remoteAddress());
            if (!empty($report)) {
                $this->fancyDie('You have already submitted a report for that post.');
            }

            $go_back = 1;
            if ($this->config->reportcaptcha != '') {
                if (isset($this->request->query['verify'])) {
                    $this->checkCaptcha($this->config->reportcaptcha);
                    $go_back = 2;
                } else {
                    { // Simple CAPTCHA
                        $captcha = '
<br>
<input type="text" name="captcha" id="captcha" size="6" accesskey="c" autocomplete="off">&nbsp;&nbsp;' . '(enter the text below)' . '<br>
<img id="captchaimage" src="inc/captcha.php" width="175" height="55" alt="CAPTCHA" onclick="javascript:reloadCAPTCHA()" style="margin-top: 5px;cursor: pointer;"><br><br>';
                    }

                    $txt_report = 'Please complete a CAPTCHA to submit your report';
                    $txt_submit = 'Submit';
                    $body = <<<EOF
                        <form id="tinyib" name="tinyib" method="post" action="?report={$post['id']}&verify">
                        <fieldset>
                        <legend align="center">$txt_report</legend>
                        <div class="login">
                        $captcha
                        <input type="submit" value="$txt_submit" class="managebutton">
                        </div>
                        </fieldset>
                        </form>
                        EOF;

                    echo $this->pageHeader() . $body . $this->pageFooter();
                    die();
                }
            }

            $report = ['ip' => $this->remoteAddress(), 'post' => $post['id']];
            $this->insertReport($report);
            $this->checkAutoHide($post);

            $this->fancyDie('Post reported.', $go_back, 200);
            // Check if the request is to delete a post and/or its associated image
        } elseif (isset($this->request->query['delete']) && !isset($this->request->query['manage'])) {
            $lock = $this->lockDatabase();

            if (!isset($this->request->form['delete'])) {
                $this->fancyDie('Tick the box next to a post and click "Delete" to delete it.');
            }

            $post_ids = [];
            if (is_array($this->request->form['delete'])) {
                $post_ids = $this->request->form['delete'];
            } else {
                $post_ids = [$this->request->form['delete']];
            }

            [$this->account, $this->loggedin, $this->isadmin] = $this->manageCheckLogIn(false);
            if (!empty($this->account)) {
                // Redirect to post moderation page
                echo '--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="0;url=' . basename($this->request->server['PHP_SELF']) . '?manage&moderate=' . implode(',', $post_ids) . '">';
                die();
            }

            $post = $this->postByID(Request::integer($post_ids[0]));
            if (!$post) {
                $this->fancyDie('Sorry, an invalid post identifier was sent. Please go back, refresh the page, and try again.');
            } elseif ($post['password'] != '' && Passwords::verify($this->request->form['password'] ?? '', $post['password'])) {
                $this->deletePost($post['id']);
                if ($post['parent'] == 0) {
                    $this->threadUpdated($post['id']);
                } else {
                    $this->threadUpdated($post['parent']);
                }
                $this->fancyDie('Post deleted.', status: 200);
            } else {
                $this->fancyDie('Invalid password.');
            }

            // Check if the request is to access the management area
        } elseif (isset($this->request->query['manage'])) {
            $lock = $this->lockDatabase();

            $text = '';
            $onload = '';
            $navbar = '&nbsp;';
            $redirect = false;
            $this->loggedin = false;
            $this->isadmin = false;
            $this->returnlink = basename($this->request->server['PHP_SELF']);

            if (isset($this->request->query['logout'])) {
                $_SESSION = [];
                session_destroy();
                die('--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="0;url=imgboard.php">');
            }

            [$this->account, $this->loggedin, $this->isadmin] = $this->manageCheckLogIn(true);

            if ($this->loggedin) {
                if ($this->isadmin) {
                    if (isset($this->request->query['rebuildall'])) {
                        $allthreads = $this->allThreads();
                        foreach ($allthreads as $thread) {
                            $this->rebuildThread($thread['id']);
                        }
                        $this->rebuildIndexes();
                        $text .= $this->manageInfo('Rebuilt board.');
                    } elseif (isset($this->request->query['modlog'])) {
                        $text .= $this->manageModerationLog($this->request->queryInt('modlog'));
                    } elseif (isset($this->request->query['reports'])) {
                        if (!$this->config->report) {
                            $this->fancyDie('Reporting is disabled.');
                        }
                        $text .= $this->manageReportsPage($this->request->query['reports']);
                    } elseif (isset($this->request->query['accounts'])) {
                        if ($this->account['role'] != Role::SuperAdministrator->value) {
                            $this->fancyDie('Access denied');
                        }

                        $id = $this->request->queryInt('accounts');
                        if (isset($this->request->form['id'])) {
                            $id = $this->request->formInt('id');
                        }
                        $a = ['id' => 0];
                        if ($id > 0) {
                            $a = $this->accountByID($id);
                            if (empty($a)) {
                                $this->fancyDie('Account not found.');
                            }
                        }

                        if (isset($this->request->form['id'])) {
                            if ($id == 0 && $this->request->form['password'] == '') {
                                $this->fancyDie('A password is required.');
                            }

                            $prev = $a;

                            $a['username'] = $this->request->form['username'];
                            if ($this->request->form['password'] != '') {
                                $a['password'] = $this->request->form['password'];
                            }
                            $a['role'] = $this->request->formInt('role');
                            if ($a['role'] !== Role::SuperAdministrator->value && $a['role'] != Role::Administrator->value && $a['role'] != Role::Moderator->value && $a['role'] != Role::Disabled->value) {
                                $this->fancyDie('Invalid role.');
                            }

                            if ($id == 0) {
                                $this->insertAccount($a);
                                $this->manageLogAction(sprintf('Added account %s', $a['username']));
                                $text .= $this->manageInfo('Added account');
                            } else {
                                $this->updateAccount($a);
                                if ($a['username'] != $prev['username']) {
                                    $this->manageLogAction(sprintf('Renamed account %1$s as %2$s', $prev['username'], $a['username']));
                                }
                                if ($a['password'] != $prev['password']) {
                                    $this->manageLogAction(sprintf('Changed password of account %s', $a['username']));
                                }
                                if ($a['role'] != $prev['role']) {
                                    $r = '';
                                    switch ($a['role']) {
                                        case Role::SuperAdministrator->value:
                                            $r = 'Super-administrator';
                                            break;
                                        case Role::Administrator->value:
                                            $r = 'Administrator';
                                            break;
                                        case Role::Moderator->value:
                                            $r = 'Moderator';
                                            break;
                                        case Role::Disabled->value:
                                            $r = 'Disabled';
                                            break;
                                    }
                                    $this->manageLogAction(sprintf('Changed role of account %s to %s', $a['username'], $r));
                                }
                                $text .= $this->manageInfo('Updated account');
                            }
                        }

                        $onload = $this->manageOnLoad('accounts');
                        $text .= $this->manageAccountForm($this->request->queryInt('accounts'));
                        if ($this->request->queryInt('accounts') == 0) {
                            $text .= $this->manageAccountsTable();
                        }
                    } elseif (isset($this->request->query['bans'])) {
                        $this->clearExpiredBans();

                        if (isset($this->request->form['ip']) && $this->request->form['ip'] != '') {
                            $ips = explode(',', $this->request->form['ip']);
                            foreach ($ips as $ip) {
                                $banexists = $this->banByIP($ip);
                                if ($banexists) {
                                    continue;
                                }

                                if ($this->config->report) {
                                    $this->deleteReportsByIP($ip);
                                }

                                $ban = [];
                                $ban['ip'] = $ip;
                                $ban['expire'] = ($this->request->form['expire'] > 0) ? (time() + $this->request->formInt('expire')) : 0;
                                $ban['reason'] = $this->request->form['reason'];

                                $until = 'permanently';
                                if ($ban['expire'] > 0) {
                                    $until = sprintf('until %s', $this->formatDate($ban['expire']));
                                }
                                $action = sprintf('Banned %s %s', htmlentities($ban['ip']), $until);
                                {
                                    $action = sprintf('Banned %s %s: %s', htmlentities($ban['ip']), $until, htmlentities($ban['reason']));
                                }

                                $this->insertBan($ban);
                                $this->manageLogAction($action);
                            }
                            if ($this->config->banmessage && isset($this->request->form['message']) && $this->request->form['message'] != '' && isset($this->request->query['posts']) && $this->request->query['posts'] != '') {
                                $post_ids = Request::ids($this->request->query['posts']);
                                foreach ($post_ids as $post_id) {
                                    $post = $this->postByID($post_id);
                                    if (!$post) {
                                        continue; // The post has been deleted
                                    }
                                    $this->updatePostMessage($post['id'], $post['message'] . '<br>' . "\n" . '<span class="banmessage">(' . htmlentities($this->request->form['message']) . ')</span><br>');
                                    $this->manageLogAction(sprintf('Added ban message to %s', $this->postLink('&gt;&gt;' . $post['id'])));
                                }

                                foreach ($post_ids as $post_id) {
                                    $post = $this->postByID($post_id);
                                    if (!$post) {
                                        continue; // The post has been deleted
                                    }
                                    $this->threadUpdated($this->getParent($post));
                                }
                            }
                            if (count($ips) == 1) {
                                $text .= $this->manageInfo('Banned 1 IP address');
                            } else {
                                $text .= $this->manageInfo(sprintf('Banned %d IP addresses', count($ips)));
                            }
                        } elseif (isset($this->request->query['lift'])) {
                            $ban = $this->banByID($this->request->queryInt('lift'));
                            if ($ban) {
                                $this->deleteBanByID($this->request->queryInt('lift'));
                                $info = sprintf('Lifted ban on %s', htmlentities($ban['ip']));
                                $this->manageLogAction($info);
                                $text .= $this->manageInfo($info);
                            }
                        }

                        $onload = $this->manageOnLoad('bans');
                        $text .= $this->manageBanForm();
                        $text .= $this->manageBansTable();
                    } elseif (isset($this->request->query['keywords'])) {
                        if (isset($this->request->form['text']) && $this->request->form['text'] != '') {
                            if ($this->request->query['keywords'] > 0) {
                                $this->deleteKeyword($this->request->queryInt('keywords'));
                            }

                            $keyword_exists = $this->keywordByText($this->request->form['text']);
                            if ($keyword_exists) {
                                $this->fancyDie('Sorry, that keyword has already been added.');
                            }

                            $keyword = [];
                            $keyword['text'] = $this->request->form['text'];
                            $keyword['action'] = $this->request->form['action'];

                            $kw = $keyword['text'];

                            if (isset($this->request->form['regexp']) && $this->request->form['regexp'] == '1') {
                                $keyword['text'] = 'regexp:' . $keyword['text'];
                            }

                            $this->insertKeyword($keyword);
                            if ($this->request->query['keywords'] > 0) {
                                $this->manageLogAction(sprintf('Updated keyword %s', htmlentities($kw)));
                                $text .= $this->manageInfo('Keyword updated.');
                                $this->request->query['keywords'] = '0';
                            } else {
                                $this->manageLogAction(sprintf('Updated keyword %s', htmlentities($kw)));
                                $text .= $this->manageInfo('Keyword added.');
                            }
                        } elseif (isset($this->request->query['deletekeyword'])) {
                            $keyword = $this->keywordByID($this->request->queryInt('deletekeyword'));
                            if (empty($keyword)) {
                                $this->fancyDie('That keyword does not exist.');
                            }

                            $kw = $keyword['text'];
                            if (substr($keyword['text'], 0, 7) == 'regexp:') {
                                $kw = substr($keyword['text'], 7);
                            }

                            $this->deleteKeyword($this->request->queryInt('deletekeyword'));
                            $this->manageLogAction(sprintf('Deleted keyword %s', htmlentities($kw)));
                            $text .= $this->manageInfo('Keyword deleted.');
                        }

                        $onload = $this->manageOnLoad('keywords');
                        if ($this->request->query['keywords'] > 0) {
                            $text .= $this->manageEditKeyword($this->request->queryInt('keywords'));
                        } else {
                            $text .= $this->manageEditKeyword(0);
                            $text .= $this->manageKeywordsTable();
                        }
                    }
                }

                if (isset($this->request->query['delete'])) {
                    $post_ids = Request::ids($this->request->query['delete']);
                    $posts = [];
                    foreach ($post_ids as $post_id) {
                        $post = $this->postByID($post_id);
                        if (!$post) {
                            continue; // The post has already been deleted
                        }
                        $posts[$post_id] = $post;
                    }
                    foreach ($post_ids as $post_id) {
                        $post = $posts[$post_id];

                        $this->deletePost($post['id']);
                        if ($post['parent'] == 0) {
                            $this->rebuildThread($post['id']);
                        } else {
                            $this->rebuildThread($post['parent']);
                        }

                        $action = sprintf('Deleted %s', '&gt;&gt;' . $post['id']) . ' - ' . $this->hashData($post['ip']);
                        $stripped = strip_tags($post['message']);
                        if ($stripped != '') {
                            $action .= ' - ' . htmlentities($this->substring($stripped, 0, 32));
                            if ($this->length($stripped) > 32) {
                                $action .= '...';
                            }
                        }
                        $this->manageLogAction($action);
                    }
                    $this->rebuildIndexes();
                    if (count($post_ids) == 1) {
                        $text .= $this->manageInfo('Deleted 1 post');
                    } else {
                        $text .= $this->manageInfo(sprintf('Deleted %d posts', count($post_ids)));
                    }
                } elseif (isset($this->request->query['approve'])) {
                    if ($this->request->query['approve'] > 0) {
                        $post = $this->postByID($this->request->queryInt('approve'));
                        if ($post) {
                            $this->approvePostByID($post['id'], 2);
                            $thread_id = $post['parent'] == 0 ? $post['id'] : $post['parent'];

                            if (strtolower($post['email']) != 'sage' && ($this->config->maxreplies == 0 || $this->numRepliesToThreadByID($thread_id) <= $this->config->maxreplies)) {
                                $this->bumpThreadByID($thread_id);
                            }
                            $this->threadUpdated($thread_id);

                            $this->manageLogAction('Approved' . ' ' . $this->postLink('&gt;&gt;' . $post['id']));
                            $text .= $this->manageInfo(sprintf('Post No.%d approved.', $post['id']));
                        } else {
                            $this->fancyDie("Sorry, there doesn't appear to be a post with that ID.");
                        }
                    }
                } elseif (isset($this->request->query['moderate'])) {
                    if ($this->request->query['moderate'] != '' && $this->request->query['moderate'] != '0') {
                        $post_ids = Request::ids($this->request->query['moderate']);
                        $compact = count($post_ids) > 1;
                        $posts = [];
                        $threads = 0;
                        $replies = 0;
                        $ips = [];

                        foreach ($post_ids as $post_id) {
                            $post = $this->postByID($post_id);
                            if (!$post) {
                                $this->fancyDie("Sorry, there doesn't appear to be a post with that ID.");
                            }
                            if ($post['parent'] == 0) {
                                $threads++;
                            } else {
                                $replies++;
                            }
                            $ips[] = $post['ip'];

                            $posts[$post_id] = $post;
                        }

                        $ips = array_unique($ips);

                        if (count($post_ids) > 1) {
                            $text .= $this->manageModerateAll($post_ids, $threads, $replies, $ips);
                        }
                        foreach ($post_ids as $post_id) {
                            $text .= $this->manageModeratePost($posts[$post_id], $compact);
                        }
                    } else {
                        $onload = $this->manageOnLoad('moderate');
                        $text .= $this->manageModeratePostForm();
                    }
                } elseif (isset($this->request->query['sticky']) && isset($this->request->query['setsticky'])) {
                    if ($this->request->query['sticky'] > 0) {
                        $post = $this->postByID($this->request->queryInt('sticky'));
                        if ($post && $post['parent'] == 0) {
                            $this->stickyThreadByID($post['id'], $this->request->queryInt('setsticky'));
                            $this->threadUpdated($post['id']);

                            $actionMessage = $this->request->queryInt('setsticky') == 1 ? 'Stickied' : 'Unstickied' . ' ' . $this->postLink('&gt;&gt;' . $post['id']);
                            $this->manageLogAction($actionMessage);
                            $text .= $this->manageInfo($actionMessage);
                        } else {
                            $this->fancyDie("Sorry, there doesn't appear to be a post with that ID.");
                        }
                    } else {
                        $this->fancyDie('Form data was lost. Please go back and try again.');
                    }
                } elseif (isset($this->request->query['lock']) && isset($this->request->query['setlock'])) {
                    if ($this->request->query['lock'] > 0) {
                        $post = $this->postByID($this->request->queryInt('lock'));
                        if ($post && $post['parent'] == 0) {
                            $this->lockThreadByID($post['id'], $this->request->queryInt('setlock'));
                            $this->threadUpdated($post['id']);

                            $actionMessage = $this->request->queryInt('setlock') == 1 ? 'Locked' : 'Unlocked' . ' ' . $this->postLink('&gt;&gt;' . $post['id']);
                            $this->manageLogAction($actionMessage);
                            $text .= $this->manageInfo($actionMessage);
                        } else {
                            $this->fancyDie("Sorry, there doesn't appear to be a post with that ID.");
                        }
                    } else {
                        $this->fancyDie('Form data was lost. Please go back and try again.');
                    }
                } elseif (isset($this->request->query['clearreports'])) {
                    if ($this->request->query['clearreports'] > 0) {
                        $post = $this->postByID($this->request->queryInt('clearreports'));
                        if ($post) {
                            $this->approvePostByID($post['id'], 2);
                            $this->deleteReportsByPost($post['id']);

                            $this->manageLogAction('Approved' . ' ' . $this->postLink('&gt;&gt;' . $post['id']));
                            $text .= $this->manageInfo(sprintf('Post No.%d approved.', $post['id']));
                        } else {
                            $this->fancyDie("Sorry, there doesn't appear to be a post with that ID.");
                        }
                    }
                } elseif (isset($this->request->query['staffpost'])) {
                    $onload = $this->manageOnLoad('staffpost');
                    $text .= $this->buildPostForm(0, true);
                } elseif (isset($this->request->query['changepassword'])) {

                    if (isset($this->request->form['password']) && isset($this->request->form['confirm'])) {
                        if ($this->request->form['password'] == '') {
                            $this->fancyDie('A password is required.');
                        } elseif ($this->request->form['password'] != $this->request->form['confirm']) {
                            $this->fancyDie('Passwords do not match.');
                        }

                        $this->account['password'] = $this->request->form['password'];
                        $this->updateAccount($this->account);

                        $text .= $this->manageInfo('Password updated');
                    }

                    $text .= $this->manageChangePasswordForm();
                }

                if ($text == '') {
                    $text = $this->manageStatus();
                }
            } else {
                $onload = $this->manageOnLoad('login');
                $text .= $this->manageLogInForm();
            }

            echo $this->managePage($text, $onload);
        } elseif (PHP_SAPI === 'cli' || !file_exists($this->config->index) || $this->countThreads() == 0) {
            $this->rebuildIndexes();
        }

        if (PHP_SAPI === 'cli') {
            echo 'Rebuilt ' . $this->config->index . PHP_EOL;
        } elseif ($redirect) {
            echo '--&gt; --&gt; --&gt;<meta http-equiv="refresh" content="' . (isset($slow_redirect) ? '3' : '0') . ';url=' . (is_string($redirect) ? $redirect : $this->config->index) . '">';
        }

    }
}
