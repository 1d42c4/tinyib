<?php

declare(strict_types=1);

namespace TinyIB;

trait Rendering
{
    private function pageHeader(): string
    {
        $title = $this->cleanString($this->config->title);
        $stylesheets = $this->pageStylesheets();

        return <<<EOF
            <!DOCTYPE html>
            <html>
            	<head>
            		<meta http-equiv="content-type" content="text/html;charset=UTF-8">
            		<meta http-equiv="cache-control" content="max-age=0">
            		<meta http-equiv="cache-control" content="no-cache">
            		<meta http-equiv="expires" content="0">
            		<meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
            		<meta http-equiv="pragma" content="no-cache">
            		<meta name="viewport" content="width=device-width,initial-scale=1">
            		<title>$title</title>
            		<link rel="shortcut icon" href="favicon.ico">
            		$stylesheets
            		<script src="js/jquery.js"></script>
            		<script src="js/tinyib.js"></script>
            <script src="js/security.js" defer></script>
            		
            	</head>
            EOF;
    }

    private function pageStylesheets(): string
    {

        // Global stylesheet
        $return = '<link rel="stylesheet" type="text/css" href="css/global.css">';

        // Default stylesheet
        $return .= '<link rel="stylesheet" type="text/css" href="css/' . htmlentities($this->config->defaultstyle, ENT_QUOTES) . '.css" title="' . htmlentities($this->config->stylesheets[$this->config->defaultstyle], ENT_QUOTES) . '" id="mainStylesheet">';

        // Additional stylesheets
        foreach ($this->config->stylesheets as $filename => $title) {
            if ($filename === $this->config->defaultstyle) {
                continue;
            }

            $return .= '<link rel="alternate stylesheet" type="text/css" href="css/' . htmlentities($filename, ENT_QUOTES) . '.css" title="' . htmlentities($title, ENT_QUOTES) . '">';
        }

        return $return;
    }

    private function pageFooter(): string
    {
        // If the footer link is removed from the page, please link to TinyIB somewhere on the site.
        // This is all I ask in return for the free software you are using.

        return <<<EOF
            		<div class="footer">
            			- <a href="http://www.2chan.net" target="_blank">futaba</a> + <a href="http://www.1chan.net" target="_blank">futallaby</a> + <a href="https://codeberg.org/tslocum/tinyib" target="_blank">tinyib</a> -
            		</div>
            	</body>
            </html>
            EOF;
    }

    private function supportedFileTypes(): string
    {

        if (empty($this->config->uploads)) {
            return '';
        }

        $types_allowed = array_map('strtoupper', array_unique(array_column($this->config->uploads, 0)));
        if (count($types_allowed) == 1) {
            return sprintf('Supported file type is %s', $types_allowed[0]);
        }
        $last_type = array_pop($types_allowed);
        return sprintf('Supported file types are %1$s and %2$s.', implode(', ', $types_allowed), $last_type);
    }

    private function linkCallback(array $matches): string
    {
        if (!isset($matches[1])) {
            return '';
        }
        $url = $this->cleanQuotes($matches[1]);
        $text = $matches[1];
        return '<a href="' . $url . '" target="_blank">' . $text . '</a>';
    }

    private function makeLinksClickable(string $text): string
    {
        $text = preg_replace_callback('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@%\!_+.,~#?&;:|\'/=]+)!i', $this->linkCallback(...), $text);
        $text = preg_replace('/\(\<a href\=\"(.*)\)"\ target\=\"\_blank\">(.*)\)\<\/a>/i', '(<a href="$1" target="_blank">$2</a>)', $text);
        $text = preg_replace('/\<a href\=\"(.*)\."\ target\=\"\_blank\">(.*)\.\<\/a>/i', '<a href="$1" target="_blank">$2</a>.', $text);
        $text = preg_replace('/\<a href\=\"(.*)\,"\ target\=\"\_blank\">(.*)\,\<\/a>/i', '<a href="$1" target="_blank">$2</a>,', $text);

        return $text;
    }

    private function buildPostForm(int $parent, bool $staff_post = false): string
    {

        $hide_fields = $parent == 0 ? $this->config->hidefieldsop : $this->config->hidefields;

        $postform_extra = ['name' => '', 'email' => '', 'subject' => '', 'footer' => ''];
        $input_submit = '<input type="submit" value="' . 'Submit' . '" accesskey="z">';
        if ($staff_post || !in_array('subject', $hide_fields)) {
            $postform_extra['subject'] = $input_submit;
        } elseif (!in_array('email', $hide_fields)) {
            $postform_extra['email'] = $input_submit;
        } elseif (!in_array('name', $hide_fields)) {
            $postform_extra['name'] = $input_submit;
        } elseif (!in_array('email', $hide_fields)) {
            $postform_extra['email'] = $input_submit;
        } else {
            $postform_extra['footer'] = $input_submit;
        }

        $form_action = 'imgboard.php';
        $form_extra = '<input type="hidden" name="parent" value="' . $parent . '">';
        $input_extra = '';
        $rules_extra = '';

        $maxlen_name = -1;
        $maxlen_email = -1;
        $maxlen_subject = -1;
        $maxlen_message = -1;
        if ($this->config->maxname > 0) {
            $maxlen_name = $this->config->maxname;
        }
        if ($this->config->maxemail > 0) {
            $maxlen_email = $this->config->maxemail;
        }
        if ($this->config->maxsubject > 0) {
            $maxlen_subject = $this->config->maxsubject;
        }
        if ($this->config->maxmessage > 0) {
            $maxlen_message = $this->config->maxmessage;
        }
        if ($staff_post) {
            $txt_raw_html = 'Raw HTML';
            $txt_enable = 'Enable';
            $txt_raw_html_info_1 = 'Text entered in the Message field will be posted as is with no formatting applied.';
            $txt_raw_html_info_2 = 'Line-breaks must be specified with "&lt;br&gt;".';

            $txt_reply_to = 'Reply to';
            $txt_new_thread = '0 to start a new thread';

            $form_action = '?';
            $form_extra = '<input type="hidden" name="staffpost" value="1">';
            $input_extra = <<<EOF
                					<tr>
                						<td class="postblock">
                							$txt_raw_html
                						</td>
                						<td>
                							<label>
                								<input type="checkbox" name="raw" value="1" accesskey="r">&nbsp;$txt_enable<br>
                							&nbsp; 	<small>$txt_raw_html_info_1</small><br>
                							&nbsp; 	<small>$txt_raw_html_info_2</small>
                							</label>
                						</td>
                					</tr>
                					<tr>
                						<td class="postblock">
                							$txt_reply_to
                						</td>
                						<td>
                							<input type="text" name="parent" size="28" maxlength="75" value="0" accesskey="t">&nbsp;$txt_new_thread
                						</td>
                					</tr>
                EOF;

            $maxlen_name = -1;
            $maxlen_email = -1;
            $maxlen_subject = -1;
            $maxlen_message = -1;
        }

        $max_file_size_input_html = '';
        $max_file_size_rules_html = '';
        $reqmod_html = '';
        $filetypes_html = '';
        $file_input_html = '';
        $embed_input_html = '';
        $unique_posts_html = '';

        $captcha_setting = $parent == 0 ? $this->config->captcha : $this->config->replycaptcha;

        $captcha_html = '';
        if ($captcha_setting && !$staff_post) {
            { // Simple CAPTCHA
                $captcha_inner_html = '
<input type="text" name="captcha" id="captcha" size="6" accesskey="c" autocomplete="off">&nbsp;&nbsp;' . '(enter the text below)' . '<br>
<img id="captchaimage" src="inc/captcha.php" width="175" height="55" alt="CAPTCHA" onclick="javascript:reloadCAPTCHA()" style="margin-top: 5px;cursor: pointer;">';
            }

            $txt_captcha = 'CAPTCHA';
            $captcha_html = <<<EOF
                					<tr>
                						<td class="postblock">
                							$txt_captcha
                						</td>
                						<td>
                							$captcha_inner_html
                						</td>
                					</tr>
                EOF;
        }

        if (!empty($this->config->uploads) && ($staff_post || !in_array('file', $hide_fields))) {
            if ($this->config->maxkb > 0) {
                $max_file_size_input_html = '<input type="hidden" name="MAX_FILE_SIZE" value="' . (string) ($this->config->maxkb * 1024) . '">';
                $max_file_size_rules_html = '<li>' . sprintf('Maximum file size allowed is %s.', $this->config->maxkbdesc) . '</li>';
            }

            $filetypes_html = '<li>' . $this->supportedFileTypes() . '</li>';

            $txt_file = 'File';
            $spoiler_html = '';
            if ($this->config->spoilerimage) {
                $spoiler_html = '<label><input type="checkbox" name="spoiler" value="1"> Spoiler</label>';
            }
            $file_input_html = <<<EOF
                					<tr>
                						<td class="postblock">
                							$txt_file
                						</td>
                						<td>
                							<input type="file" name="file" size="35" accesskey="f">
                							$spoiler_html
                						</td>
                					</tr>
                EOF;
        }

        $embeds_enabled = (!empty($this->config->embeds) || $this->config->uploadviaurl) && ($staff_post || !in_array('embed', $hide_fields));
        if ($embeds_enabled) {
            $txt_embed = 'Embed';
            $txt_embed_help = '';
            $txt_embed_help = '(paste a YouTube URL)';
            $embed_input_html = <<<EOF
                					<tr>
                						<td class="postblock">
                							$txt_embed
                						</td>
                						<td>
                							<input type="text" name="embed" size="28" accesskey="x" autocomplete="off">&nbsp;&nbsp;$txt_embed_help
                						</td>
                					</tr>
                EOF;
        }

        if ($this->config->reqmod == 'all') {
            $reqmod_html = '<li>' . 'All posts are moderated before being shown.' . '</li>';
        } elseif ($this->config->reqmod == 'files') {
            $reqmod_html = '<li>' . 'All posts with a file attached are moderated before being shown.' . '</li>';
        }

        $thumbnails_html = '';
        if (isset($this->config->uploads['image/jpeg']) || isset($this->config->uploads['image/pjpeg']) || isset($this->config->uploads['image/png']) || isset($this->config->uploads['image/gif'])) {
            $maxdimensions = $this->config->maxwop . 'x' . $this->config->maxhop;
            if ($this->config->maxw != $this->config->maxwop || $this->config->maxh != $this->config->maxhop) {
                $maxdimensions .= ' (new thread) or ' . $this->config->maxw . 'x' . $this->config->maxh . ' (reply)';
            }

            $thumbnails_html = '<li>' . sprintf('Images greater than %s will be thumbnailed.', $maxdimensions) . '</li>';
        }

        $unique_posts = $this->uniquePosts();
        if ($unique_posts > 0) {
            $unique_posts_html = '<li>' . sprintf('Currently %s unique user posts.', $unique_posts) . '</li>' . "\n";
        }

        $output = <<<EOF
            		<div class="postarea">
            			<form name="postform" id="postform" action="$form_action" method="post" enctype="multipart/form-data">
            			$max_file_size_input_html
            			$form_extra
            			<table class="postform">
            				<tbody>
            					$input_extra
            EOF;
        if ($staff_post || !in_array('name', $hide_fields)) {
            $txt_name = 'Name';
            $output .= <<<EOF
                					<tr>
                						<td class="postblock">
                							$txt_name
                						</td>
                						<td>
                							<input type="text" name="name" size="28" maxlength="{$maxlen_name}" accesskey="n">
                							{$postform_extra['name']}
                						</td>
                					</tr>
                EOF;
        }
        if ($staff_post || !in_array('email', $hide_fields)) {
            $txt_email = 'E-mail';
            $output .= <<<EOF
                					<tr>
                						<td class="postblock">
                							$txt_email
                						</td>
                						<td>
                							<input type="text" name="email" size="28" maxlength="{$maxlen_email}" accesskey="e">
                							{$postform_extra['email']}
                						</td>
                					</tr>
                EOF;
        }
        if ($staff_post || !in_array('subject', $hide_fields)) {
            $txt_subject = 'Subject';
            $output .= <<<EOF
                					<tr>
                						<td class="postblock">
                							$txt_subject
                						</td>
                						<td>
                							<input type="text" name="subject" size="40" maxlength="{$maxlen_subject}" accesskey="s" autocomplete="off">
                							{$postform_extra['subject']}
                						</td>
                					</tr>
                EOF;
        }
        if ($staff_post || !in_array('message', $hide_fields)) {
            $txt_message = 'Message';
            $output .= <<<EOF
                					<tr>
                						<td class="postblock">
                							$txt_message
                						</td>
                						<td>
                							<textarea id="message" name="message" cols="48" rows="4" maxlength="{$maxlen_message}" accesskey="m"></textarea>
                						</td>
                					</tr>
                EOF;
        }

        $output .= <<<EOF
            					$captcha_html
            					$file_input_html
            					$embed_input_html
            EOF;
        if ($staff_post || !in_array('password', $hide_fields)) {
            $txt_password = 'Password';
            $txt_password_help = '(for post and file deletion)';
            $output .= <<<EOF
                					<tr>
                						<td class="postblock">
                							$txt_password
                						</td>
                						<td>
                							<input type="password" name="password" id="newpostpassword" size="8" accesskey="p">&nbsp;&nbsp;$txt_password_help
                						</td>
                					</tr>
                EOF;
        }
        if ($postform_extra['footer'] != '') {
            $output .= <<<EOF
                					<tr>
                						<td>
                							&nbsp;
                						</td>
                						<td>
                							{$postform_extra['footer']}
                						</td>
                					</tr>
                EOF;
        }
        $output .= <<<EOF
            					<tr>
            						<td colspan="2" class="rules">
            							$rules_extra
            							<ul>
            								$reqmod_html
            								$filetypes_html
            								$max_file_size_rules_html
            								$thumbnails_html
            								$unique_posts_html
            							</ul>
            						</td>
            					</tr>
            				</tbody>
            			</table>
            			</form>
            		</div>
            EOF;

        return $output;
    }

    private function backlinks(array $post): string
    {
        if (!$this->config->backlinks) {
            return '';
        }

        $posts = $this->postsInThreadByID($this->getParent($post));
        $needle = '&gt;&gt;' . $post['id'];
        $return = '';
        foreach ($posts as $reply) {
            if (strpos($reply['message'], $needle) !== false) {
                if ($return != '') {
                    $return .= ', ';
                }
                $return .= $this->postLink('&gt;&gt;' . $reply['id']);
            }
        }
        if ($return != '') {
            $return = '&nbsp;' . $return;
        }
        return ' <small><span id="backlinks' . $post['id'] . '" class="backlinks">' . $return . '</span></small>';
    }

    private function buildPost(array $post, bool $res, bool $compact = false): string
    {
        $return = '';
        $threadid = ($post['parent'] == 0) ? $post['id'] : $post['parent'];

        if ($this->config->report) {
            $reflink = '<a href="imgboard.php?report=' . $post['id'] . '" title="' . 'Report' . '">R</a> ';
        } else {
            $reflink = '';
        }

        if ($res == true) {
            $reflink .= "<a href=\"$threadid.html#{$post['id']}\">No.</a><a href=\"$threadid.html#q{$post['id']}\" onclick=\"javascript:quotePost('{$post['id']}')\">{$post['id']}</a>";
        } else {
            $reflink .= "<a href=\"res/$threadid.html#{$post['id']}\">No.</a><a href=\"res/$threadid.html#q{$post['id']}\">{$post['id']}</a>";
        }

        if ($post['stickied'] == 1) {
            $reflink .= ' <img src="sticky.png" alt="' . 'Stickied' . '" title="' . 'Stickied' . '" width="16" height="16">';
        }

        if ($post['locked'] == 1) {
            $reflink .= ' <img src="lock.png" alt="' . 'Locked' . '" title="' . 'Locked' . '" width="16" height="16">';
        }

        if (!isset($post['omitted'])) {
            $post['omitted'] = 0;
        }

        $filehtml = '';
        $filesize = '';
        $expandhtml = '';
        $direct_link = $this->isEmbed($post['file_hex']) ? '#' : (($res == true ? '../' : '') . 'src/' . $post['file']);

        if ($post['parent'] == 0 && $post['file'] != '') {
            $filesize .= ($this->isEmbed($post['file_hex']) ? 'Embed' : 'File') . ': ';
        }

        $w = $this->config->expandwidth;
        if ($this->isEmbed($post['file_hex'])) {
            $expandhtml = $post['file'];
        } elseif (substr($post['file'], -5) == '.webm' || substr($post['file'], -4) == '.mp4') {
            $dimensions = 'width="500" height="50"';
            if ($post['image_width'] > 0 && $post['image_height'] > 0) {
                $dimensions = 'width="' . $post['image_width'] . '" height="' . $post['image_height'] . '"';
            }
            $expandhtml = <<<EOF
                <video $dimensions style="position: static; pointer-events: inherit; display: inline; max-width: {$w}vw; height: auto; max-height: 100%;" controls autoplay loop>
                	<source src="$direct_link"></source>
                </video>
                EOF;
        } elseif (in_array(substr($post['file'], -4), ['.jpg', '.png', '.gif'])) {
            $expandhtml = "<a href=\"$direct_link\" onclick=\"return expandFile(event, '{$post['id']}');\"><img src=\"" . ($res == true ? '../' : '') . "src/{$post['file']}\" width=\"{$post['image_width']}\" style=\"min-width: {$post['thumb_width']}px;min-height: {$post['thumb_height']}px;max-width: {$w}vw;height: auto;\"></a>";
        }

        $thumblink = "<a href=\"$direct_link\" target=\"_blank\"" . (($this->isEmbed($post['file_hex']) || in_array(substr($post['file'], -4), ['.jpg', '.png', '.gif', 'webm', '.mp4'])) ? " onclick=\"return expandFile(event, '{$post['id']}');\"" : '') . '>';
        $expandhtml = rawurlencode($expandhtml);

        if ($this->isEmbed($post['file_hex'])) {
            $filesize .= "<a href=\"$direct_link\" onclick=\"return expandFile(event, '{$post['id']}');\">{$post['file_original']}</a>&ndash;({$post['file_hex']})";
        } elseif ($post['file'] != '') {
            $filesize .= $thumblink . "{$post['file']}</a>&ndash;({$post['file_size_formatted']}";
            if ($post['image_width'] > 0 && $post['image_height'] > 0) {
                $filesize .= ', ' . $post['image_width'] . 'x' . $post['image_height'];
            }
            if ($post['file_original'] != '') {
                $filesize .= ', ' . $post['file_original'];
            }
            $filesize .= ')';
        }

        if ($filesize != '') {
            $filesize = '<span class="filesize">' . $filesize . '</span>';
        }

        if ($filesize != '') {
            if ($post['parent'] != 0) {
                $filehtml .= '<br>';
            }
            $filehtml .= $filesize . '<br><div id="thumbfile' . $post['id'] . '">';
            if ($post['thumb_width'] > 0 && $post['thumb_height'] > 0) {
                $filehtml .= <<<EOF
                    $thumblink
                    	<img src="thumb/{$post['thumb']}" alt="{$post['id']}" class="thumb" id="thumbnail{$post['id']}" width="{$post['thumb_width']}" height="{$post['thumb_height']}">
                    </a>
                    EOF;
            }
            $filehtml .= '</div>';

            if ($expandhtml != '') {
                $filehtml .= <<<EOF
                    <div id="expand{$post['id']}" style="display: none;">$expandhtml</div>
                    <div id="file{$post['id']}" class="thumb" style="display: none;"></div>
                    EOF;
            }
        }
        if ($post['parent'] == 0) {
            $return .= '<div id="post' . $post['id'] . '" class="op">';
            $return .= $filehtml;
        } else {
            if ($compact) {
                $return .= '<div id="' . $post['id'] . '" class="' . ($post['parent'] == 0 ? 'op' : 'reply') . '">';
            } else {
                $return .= <<<EOF
                    <table>
                    <tbody>
                    <tr>
                    <td class="doubledash">
                    	&#0168;
                    </td>
                    <td class="reply" id="post{$post['id']}">
                    EOF;
            }
        }

        $return .= <<<EOF
            <a id="{$post['id']}"></a>
            <label>
            	<input type="checkbox" name="delete[]" value="{$post['id']}"> 
            EOF;

        if ($post['subject'] != '') {
            $return .= ' <span class="filetitle">' . $post['subject'] . '</span> ';
        }

        $return .= <<<EOF
            {$post['nameblock']}
            </label>
            <span class="reflink">
            	$reflink
            </span>
            EOF;

        if ($post['parent'] != 0) {
            $return .= $this->backlinks($post);
        }

        if ($post['parent'] != 0) {
            $return .= $filehtml;
        }

        if ($post['parent'] == 0) {
            if ($res == false) {
                $return .= "&nbsp;[<a href=\"res/{$post['id']}.html\">" . 'Reply' . '</a>]';
            }
            $return .= $this->backlinks($post);
        }

        if ($this->config->truncate > 0 && !$res && $this->countOccurrences($post['message'], '<br>') > $this->config->truncate) { // Truncate messages on board index pages for readability
            $br_offsets = $this->strallpos($post['message'], '<br>');
            $post['message'] = $this->substring($post['message'], 0, $br_offsets[$this->config->truncate - 1]);
            $post['message'] .= '<br><span class="omittedposts">' . 'Post truncated. Click Reply to view.' . '</span><br>';
        }
        $return .= <<<EOF
            <div class="message">
            {$post['message']}
            </div>
            EOF;

        if ($post['parent'] == 0) {
            $return .= '</div>';
            if ($res == false && $post['omitted'] > 0) {
                if ($post['omitted'] == 1) {
                    $return .= '<span class="omittedposts">' . '1 post omitted. Click Reply to view.' . '</span>';
                } else {
                    $return .= '<span class="omittedposts">' . sprintf('%d posts omitted. Click Reply to view.', $post['omitted']) . '</span>';
                }
            }
        } elseif ($compact) {
            $return .= '</div>';
        } else {
            $return .= <<<EOF
                </td>
                </tr>
                </tbody>
                </table>
                EOF;
        }

        return $return;
    }

    private function buildPage(string $htmlposts, int $parent, int $pages = 0, int $thispage = 0, int $lastpostid = 0): string
    {

        $cataloglink = $this->config->catalog ? ('[<a href="catalog.html" style="text-decoration: underline;">' . 'Catalog' . '</a>]') : '';
        $managelink = ($this->config->managekey == '') ? ('[<a href="' . basename($this->request->server['PHP_SELF']) . '?manage"" style="text-decoration: underline;">' . 'Manage' . '</a>]') : '';

        $postingmode = '';
        $pagenavigator = '';
        if ($parent == 0) {
            $pages = max($pages, 0);
            $previous = ($thispage == 1) ? 'index' : $thispage - 1;
            $next = $thispage + 1;

            $pagelinks = ($thispage == 0) ? ('<td>' . 'Previous' . '</td>') : ('<td><form method="get" action="' . $previous . '.html"><input value="' . 'Previous' . '" type="submit"></form></td>');

            $pagelinks .= '<td>';
            for ($i = 0; $i <= $pages; $i++) {
                if ($thispage == $i) {
                    $pagelinks .= '&#91;' . $i . '&#93; ';
                } else {
                    $href = ($i == 0) ? 'index' : $i;
                    $pagelinks .= '&#91;<a href="' . $href . '.html">' . $i . '</a>&#93; ';
                }
            }
            $pagelinks .= '</td>';

            $pagelinks .= ($pages <= $thispage) ? ('<td>' . 'Next' . '</td>') : ('<td><form method="get" action="' . $next . '.html"><input value="' . 'Next' . '" type="submit"></form></td>');

            $pagenavigator = <<<EOF
                <table border="1" style="display: inline-block;">
                	<tbody>
                		<tr>
                			$pagelinks
                		</tr>
                	</tbody>
                </table>
                EOF;
            if ($this->config->catalog) {
                $txt_catalog = 'Catalog';
                $pagenavigator .= <<<EOF
                    <table border="1" style="display: inline-block;margin-left: 21px;">
                    	<tbody>
                    		<tr>
                    			<td><form method="get" action="catalog.html"><input value="$txt_catalog" type="submit"></form></td>
                    		</tr>
                    	</tbody>
                    </table>
                    EOF;
            }
        } elseif ($parent == -1) {
            $postingmode = '&#91;<a href="index.html">' . 'Return' . '</a>&#93;<div class="replymode">' . 'Catalog' . '</div> ';
        } else {
            $postingmode = '&#91;<a href="../">' . 'Return' . '</a>&#93;<div class="replymode">' . 'Posting mode: Reply' . '</div> ';
        }

        $postform = '';
        if ($parent >= 0) { // Negative values indicate the post form should be hidden
            $postform = $this->buildPostForm($parent) . '<hr>';
        }

        $js = '<script type="text/javascript">';
        $js .= 'var enablebacklinks = ' . ($this->config->backlinks ? 'true' : 'false') . ';';
        if ($parent != 0 && $this->config->autorefresh > 0) {
            $js .= 'var autoRefreshDelay = ' . $this->config->autorefresh . ';';
            $js .= 'var autoRefreshThreadID = ' . $parent . ';';
            $js .= 'var autoRefreshPostID = ' . $lastpostid . ';';
        }
        $js .= '</script>';

        $txt_style = 'Style';
        $txt_password = 'Password';
        $txt_delete = 'Delete';
        $txt_delete_post = 'Delete Post';

        $select_style = '';
        if (count($this->config->stylesheets) > 1) {
            $select_style = '<select id="switchStylesheet">';

            $select_style .= '<option value="">' . $txt_style . '</option>';
            foreach ($this->config->stylesheets as $filename => $title) {
                $select_style .= '<option value="' . htmlentities($filename, ENT_QUOTES) . '">' . htmlentities($title) . '</option>';
            }

            $select_style .= '</select>';
        }

        $body = <<<EOF
            	<body>
            		<div class="adminbar">
            			$cataloglink
            			$managelink
            			$select_style
            		</div>
            		<div class="logo">
            EOF;
        $body .= $this->config->logo . $this->config->boarddesc . <<<EOF
            		</div>
            		<hr width="90%">
            		$postingmode
            		$postform
            		$js
            		<form id="delform" action="imgboard.php?delete" method="post">
            		<input type="hidden" name="board" 
            EOF;
        $body .= 'value="' . $this->config->board . '">' . <<<EOF
            		<div id="posts">
            		$htmlposts
            		</div>
            		<hr>
            		<table class="userdelete">
            			<tbody>
            				<tr>
            					<td>
            						$txt_delete_post <input type="password" name="password" id="deletepostpassword" size="8" placeholder="$txt_password">&nbsp;<input name="deletepost" value="$txt_delete" type="submit">
            					</td>
            				</tr>
            			</tbody>
            		</table>
            		</form>
            		$pagenavigator
            		<br>
            EOF;
        return $this->pageHeader() . $body . $this->pageFooter();
    }

    private function buildCatalogPost(array $post): string
    {
        $maxwidth = max(100, $post['thumb_width']);
        $thumb = '#' . $post['id'];
        if ($post['thumb'] != '') {
            $thumb = <<<EOF
                		<img src="thumb/{$post['thumb']}" alt="{$post['id']}" width="{$post['thumb_width']}" height="{$post['thumb_height']}" border="0">
                EOF;
        }
        $replies = $this->numRepliesToThreadByID($post['id']);
        $subject = trim($post['subject']) != '' ? $post['subject'] : $this->substring(trim(str_ireplace("\n", '', strip_tags($post['message']))), 0, 75);

        return <<<EOF
            <div class="catalogpost" style="max-width: {$maxwidth}px;">
            	<a href="res/{$post['id']}.html">
            		$thumb
            	</a><br>
            	<b>$replies</b><br>
            	$subject
            </div>
            EOF;
    }

    private function rebuildCatalog(): void
    {
        $threads = $this->allThreads();
        $htmlposts = '';
        foreach ($threads as $post) {
            $htmlposts .= $this->buildCatalogPost($post);
        }

        $this->writePage('catalog.html', $this->buildPage($htmlposts, -1));
    }

    private function rebuildIndexes(): void
    {
        $page = 0;
        $i = 0;
        $htmlposts = '';
        $threads = $this->allThreads();
        $pages = max(0, intdiv(count($threads) - 1, $this->config->threadsperpage));

        foreach ($threads as $thread) {
            $replies = $this->postsInThreadByID($thread['id']);
            $thread['omitted'] = max(0, count($replies) - $this->config->previewreplies - 1);

            // Build replies for preview
            $htmlreplies = [];
            for ($j = count($replies) - 1; $j > $thread['omitted']; $j--) {
                $htmlreplies[] = $this->buildPost($replies[$j], false);
            }

            if ($i > 0) {
                $htmlposts .= "\n<hr>";
            }
            $htmlposts .= $this->buildPost($thread, false) . implode('', array_reverse($htmlreplies));

            if (++$i >= $this->config->threadsperpage) {
                $file = ($page == 0) ? $this->config->index : ($page . '.html');
                $this->writePage($file, $this->buildPage($htmlposts, 0, $pages, $page));

                $page++;
                $i = 0;
                $htmlposts = '';
            }
        }

        if ($page == 0 || $htmlposts != '') {
            $file = ($page == 0) ? $this->config->index : ($page . '.html');
            $this->writePage($file, $this->buildPage($htmlposts, 0, $pages, $page));
        }

        foreach (glob('[0-9]*.html') ?: [] as $filename) {
            if (preg_match('/^([0-9]+)\.html$/D', $filename, $matches) && (int) $matches[1] > $pages) {
                unlink($filename);
            }
        }

        if ($this->config->catalog) {
            $this->rebuildCatalog();
        }

        if ($this->config->json) {
            $this->writePage('threads.json', $this->buildIndexJson());
            $this->writePage('catalog.json', $this->buildCatalogJson());
        }
    }

    private function rebuildThread(int $id): void
    {
        $id = (int) $id;

        $post = $this->postByID($id);
        if (empty($post) || $post['moderated'] == 0) {
            if (is_file('res/' . $id . '.html')) {
                unlink('res/' . $id . '.html');
            }
            if (is_file('res/' . $id . '.json')) {
                unlink('res/' . $id . '.json');
            }
            return;
        }

        $posts = $this->postsInThreadByID($id);
        if (count($posts) == 0) {
            if (is_file('res/' . $id . '.html')) {
                unlink('res/' . $id . '.html');
            }
            if (is_file('res/' . $id . '.json')) {
                unlink('res/' . $id . '.json');
            }
            return;
        }

        $htmlposts = '';
        $lastpostid = 0;
        foreach ($posts as $post) {
            $htmlposts .= $this->buildPost($post, true);
            $lastpostid = $post['id'];
        }

        $this->writePage('res/' . $id . '.html', $this->fixLinksInRes($this->buildPage($htmlposts, $id, 0, 0, $lastpostid)));

        if ($this->config->json) {
            $this->writePage('res/' . $id . '.json', $this->buildSingleThreadJson($id));
        }
    }

    private function adminBar(): string
    {

        $return = '[<a href="' . $this->returnlink . '" style="text-decoration: underline;">' . 'Return' . '</a>]';
        if (!$this->loggedin) {
            return $return;
        }

        $output = '';
        if ($this->isadmin) {
            if ($this->account['role'] == Role::SuperAdministrator->value) {
                $output .= ' [<a href="?manage&accounts">' . 'Accounts' . '</a>]';
            }
            $output .= ' [<a href="?manage&bans">' . 'Bans' . '</a>]';
            $output .= ' [<a href="?manage&keywords">' . 'Keywords' . '</a>]';

        }
        $output .= ' [<a href="?manage&moderate">' . 'Moderate Post' . '</a>]';
        if ($this->isadmin) {
            $output .= ' [<a href="?manage&modlog">' . 'Moderation Log' . '</a>]';
            $output .= ' [<a href="?manage&rebuildall">' . 'Rebuild All' . '</a>]';
            if ($this->config->report) {
                $output .= ' [<a href="?manage&reports">' . 'Reports' . '</a>]';
            }
        }
        $output .= ' [<a href="?manage&staffpost">' . 'Staff Post' . '</a>]';
        $output .= ' [<a href="?manage">' . 'Status' . '</a>]';

        $output .= ' &middot;  [<a href="?manage&changepassword">' . 'Change Password' . '</a>]';
        $output .= ' [<a href="?manage&logout">' . 'Log Out' . '</a>]';
        $output .= ' &middot; ' . $return;
        return $output;
    }

    private function managePage(string $text, string $onload = ''): string
    {
        $adminbar = $this->adminBar();
        $txt_manage_mode = 'Manage mode';
        $body = <<<EOF
            	<body$onload>
            		<div class="adminbar">
            			$adminbar
            		</div>
            		<div class="logo">
            EOF;
        $body .= $this->config->logo . $this->config->boarddesc . <<<EOF
            		</div>
            		<hr width="90%">
            		<div class="replymode">$txt_manage_mode</div>
            		$text
            		<hr>
            EOF;
        return $this->pageHeader() . $body . $this->pageFooter();
    }

    private function manageOnLoad(string $page): string
    {
        $field = match ($page) {
            'accounts', 'login' => 'username', 'bans' => 'ip', 'keywords' => 'text', 'moderate' => 'moderate', 'staffpost' => 'message', default => '',
        };
        return $field === '' ? '' : ' onload="document.tinyib.' . $field . '.focus();"';
    }

    private function manageLogInForm(): string
    {
        $txt_login = 'Log In';
        $txt_login_prompt = 'Enter a username and password';
        $captcha_inner_html = '';
        if ($this->config->managecaptcha === 'simple') {
            $captcha_inner_html = '
<br>
<input type="text" name="captcha" id="captcha" size="6" accesskey="c" autocomplete="off">&nbsp;&nbsp;' . '(enter the text below)' . '<br>
<img id="captchaimage" src="inc/captcha.php" width="175" height="55" alt="CAPTCHA" onclick="javascript:reloadCAPTCHA()" style="margin-top: 5px;cursor: pointer;"><br><br>';
        }
        $managekey = htmlentities($this->request->query['manage'], ENT_QUOTES);
        return <<<EOF
            	<form id="tinyib" name="tinyib" method="post" action="?manage=$managekey">
            	<fieldset>
            	<legend align="center">$txt_login_prompt</legend>
            	<div class="login">
            	<input type="text" id="username" name="username" placeholder="Username"><br>
            	<input type="password" id="managepassword" name="managepassword" placeholder="Password"><br>
            	$captcha_inner_html
            	<input type="submit" value="$txt_login" class="managebutton">
            	</div>
            	</fieldset>
            	</form>
            	<br>
            EOF;
    }

    private function manageModerationLog(int $offset): string
    {
        $offset = (int) $offset;
        $limit = 50;

        $logs = $this->getLogs($offset, $limit);

        $u = [];

        $text = '';
        foreach ($logs as $log) {
            if (!isset($u[$log['account']])) {
                $username = '';
                if ($log['account'] > 0) {
                    $a = $this->accountByID($log['account']);
                    if (!empty($a)) {
                        $username = $a['username'];
                    }
                }
                $u[$log['account']] = $username;
            }
            $text .= '<tr><td>' . $this->formatDate($log['timestamp']) . '</td><td>' . htmlentities($u[$log['account']]) . '</td><td>' . $log['message'] . '</td></tr>';
        }

        if ($text == '') {
            $text = '<i>' . 'No logs.' . '</i>';
        }

        $txt_moderation_log = 'Moderation log';
        $nav = '';
        if ($offset > 0) {
            $nav .= '<a href="?manage&modlog=' . ($offset - $limit) . '">Previous ' . $limit . '</a> ';
        }
        if (count($logs) == $limit) {
            $nav .= '<a href="?manage&modlog=' . ($offset + $limit) . '">Next ' . $limit . '</a> ';
        }
        $nav_top = '';
        $nav_bottom = '';
        if ($nav != '') {
            $nav_top = $nav . '<br><br>';
            $nav_bottom = '<br><br>' . $nav;
        }
        return <<<EOF
            		$nav_top
            		<fieldset>
            		<legend>$txt_moderation_log</legend>
            		<table border="0" cellspacing="0" cellpadding="0" width="100%">
            		<tr><th align="left">Date/time</th><th align="left">Account</th><th align="left">Action</th></tr>
            		$text
            		</table>
            		</fieldset>
            		$nav_bottom
            EOF;
    }

    private function manageReportsPage(string $ip): string
    {
        $reports = $this->allReports();
        $report_counts = [];
        $posts = [];
        foreach ($reports as $report) {
            if ($ip != '' && $report['ip'] != $ip && $report['ip'] != $this->hashData($ip)) {
                continue;
            }

            $post = $this->postByID($report['post']);
            if (empty($post)) {
                continue;
            }

            if ($ip == '') {
                $post['reportedby'] = $report['ip'];

                if (!isset($report_counts[$report['ip']])) {
                    $report_counts[$report['ip']] = 0;
                }
                $report_counts[$report['ip']]++;
            }

            $posts[] = $post;
        }

        $txt_reported = 'Reported posts';
        if ($ip != '') {
            if (count($posts) == 1) {
                $format = '%1$d report by %2$s';
            } else {
                $format = '%1$d reports by %2$s';
            }
            $txt_reported = sprintf($format, count($posts), '<a href="?manage&bans=' . htmlentities($ip, ENT_QUOTES) . '">' . htmlentities($ip) . '</a>');
        }

        $post_html = '';
        foreach ($posts as $post) {
            if ($post_html != '') {
                $post_html .= '<tr><td colspan="2"><hr></td></tr>';
            }

            if (isset($post['reportedby'])) {
                $reportedby_html = '<a href="?manage&bans=' . htmlentities($post['reportedby'], ENT_QUOTES) . '">' . htmlentities($post['reportedby']) . '</a>';
                if ($report_counts[$post['reportedby']] > 1) {
                    $reportedby_html .= ' <a href="?manage&reports=' . htmlentities($post['reportedby'], ENT_QUOTES) . '">(' . sprintf('%d reports', $report_counts[$post['reportedby']]) . ')</a>';
                }

                $post_html .= '<tr><td colspan=""><small>' . sprintf('Reported by %s', $reportedby_html) . '</small></td></tr>';
            }

            $post_html .= '<tr><td>' . $this->buildPost($post, false) . '</td><td valign="top" align="right"><form method="get" action="?"><input type="hidden" name="manage" value=""><input type="hidden" name="moderate" value="' . $post['id'] . '"><input type="submit" value="' . 'Moderate' . '" class="managebutton"></form></td></tr>';
        }

        if ($post_html == '') {
            $post_html = '<i>' . 'There are currently no reported posts.' . '</i>';
        }

        return <<<EOF
            		<fieldset>
            		<legend>$txt_reported</legend>
            		<table border="0" cellspacing="0" cellpadding="0" width="100%">
            		$post_html
            		</table>
            		</fieldset>
            EOF;
    }

    private function manageChangePasswordForm(): string
    {
        $txt_header = 'Change Password';
        $txt_submit = 'Submit';
        return <<<EOF
            	<form id="tinyib" name="tinyib" method="post" action="?manage&changepassword">
            	<fieldset>
            	<legend>$txt_header</legend>
            	<table border="0">
            	<tr><td>New password</td><td><input type="password" name="password" id="password" value=""></td></tr>
            	<tr><td>Confirm</td><td><input type="password" name="confirm" id="confirm" value=""></td></tr>
            	<tr><td>&nbsp;</td><td><input type="submit" value="$txt_submit" class="managebutton"></td></tr>
            	</table>
            	<legend>
            	</fieldset>
            	</form><br>
            EOF;
    }

    private function manageAccountForm(int $id = 0): string
    {
        $a = [
            'id' => 0,
            'username' => '',
            'password' => '',
            'role' => 0,
        ];
        $txt_header = 'Add an account';
        $txt_password_hint = '';
        if ($id > 0) {
            $txt_header = 'Update an account';
            $txt_password_hint = '(' . 'Leave blank to maintain current password' . ')';
            $a = $this->accountByID($id);
        }

        $a['username'] = htmlentities($a['username'], ENT_QUOTES);

        $txt_username = 'Username';
        $txt_password = 'Password';
        $txt_role = 'Role';
        $return = <<<EOF
            	<form id="tinyib" name="tinyib" method="post" action="?manage&accounts">
            	<input type="hidden" name="id" value="{$a['id']}">
            	<fieldset>
            	<legend>$txt_header</legend>
            	<table border="0">
            	<tr><td><label for="username">$txt_username</label></td><td><input type="text" name="username" id="username" value="{$a['username']}"></td></tr>
            	<tr><td><label for="password">$txt_password</label></td><td><input type="password" name="password" id="password" value=""> <small>$txt_password_hint</small></td></tr>
            	<tr><td><label for="role">$txt_role</label></td><td><select name="role" id="role">
            EOF;
        $return .= '<option value="0" ' . ($a['role'] == 0 ? ' selected' : '') . '>' . 'Choose a role' . '</option>';
        $return .= '<option value="1" ' . ($a['role'] == 1 ? ' selected' : '') . '>' . 'Super-administrator' . '</option>';
        $return .= '<option value="2" ' . ($a['role'] == 2 ? ' selected' : '') . '>' . 'Administrator' . '</option>';
        $return .= '<option value="3" ' . ($a['role'] == 3 ? ' selected' : '') . '>' . 'Moderator' . '</option>';
        $return .= '<option value="99" ' . ($a['role'] == 99 ? ' selected' : '') . '>' . 'Disabled' . '</option>';
        $txt_submit = 'Submit';
        $return .= <<<EOF
            	</select></td></tr>
            	<tr><td>&nbsp;</td><td><input type="submit" value="$txt_submit" class="managebutton"></td></tr>
            	</table>
            	</fieldset>
            	</form><br>
            EOF;
        return $return;
    }

    private function manageAccountsTable(): string
    {
        $text = '';
        $allaccounts = $this->allAccounts();
        if (count($allaccounts) > 0) {
            $text .= '<table border="1"><tr><th>' . 'Username' . '</th><th>' . 'Role' . '</th><th>' . 'Last active' . '</th><th>&nbsp;</th></tr>';
            foreach ($allaccounts as $account) {
                $lastactive = ($account['lastactive'] > 0) ? $this->formatDate($account['lastactive']) : 'Never';
                $text .= '<tr><td>' . htmlentities($account['username']) . '</td><td>';
                switch ((int) ($account['role'])) {
                    case Role::SuperAdministrator->value:
                        $text .= 'Super-administrator';
                        break;
                    case Role::Administrator->value:
                        $text .= 'Administrator';
                        break;
                    case Role::Moderator->value:
                        $text .= 'Moderator';
                        break;
                    case Role::Disabled->value:
                        $text .= 'Disabled';
                        break;
                }
                $text .= '</td><td>' . $lastactive . '</td><td><a href="?manage&accounts=' . $account['id'] . '">' . 'update' . '</a></td></tr>';
            }
            $text .= '</table>';
        }
        return $text;
    }

    private function manageBanForm(): string
    {
        $txt_ban = 'Add a ban';
        $txt_ban_help = 'Multiple IP addresses may be banned at once by separating each address with a comma.';
        $txt_ban_ip = 'IP Address';
        $txt_ban_expire = 'Expire(sec)';
        $txt_ban_reason = 'Reason';
        $txt_ban_never = 'never';
        $txt_ban_optional = 'Optional.';
        $txt_submit = 'Submit';
        $txt_1h = '1 hour';
        $txt_1d = '1 day';
        $txt_2d = '2 days';
        $txt_1w = '1 week';
        $txt_2w = '2 weeks';
        $txt_1m = '1 month';
        $banmessage_html = '';
        $post_ids = '';
        if ($this->config->banmessage && isset($this->request->query['posts']) && $this->request->query['posts'] != '') {
            $post_ids = htmlentities($this->request->query['posts'], ENT_QUOTES);
            $banmessage_html = '<tr><td><label for="message">' . 'Message' . '</label></td><td><input type="text" name="message" id="message"></td><td><small>' . 'Append a message to the post. Optional.' . '</small></td></tr>';
        }
        $ip = htmlentities($this->request->query['bans'], ENT_QUOTES);
        return <<<EOF
            	<form id="tinyib" name="tinyib" method="post" action="?manage&bans&posts=$post_ids">
            	<fieldset>
            	<legend>$txt_ban</legend>
            	<table border="0">
            	<tr><td><label for="ip">$txt_ban_ip</label></td><td><input type="text" name="ip" id="ip" value="$ip"></td><td><input type="submit" value="$txt_submit" class="managebutton"></td></tr>
            	<tr><td><label for="expire">$txt_ban_expire</label></td><td><input type="text" name="expire" id="expire" value="0"></td><td><small><a href="#" onclick="document.tinyib.expire.value='3600';return false;">$txt_1h</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='86400';return false;">$txt_1d</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='172800';return false;">$txt_2d</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='604800';return false;">$txt_1w</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='1209600';return false;">$txt_2w</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='2592000';return false;">$txt_1m</a>&nbsp;<a href="#" onclick="document.tinyib.expire.value='0';return false;">$txt_ban_never</a></small></td></tr>
            	<tr><td><label for="reason">$txt_ban_reason</label></td><td><input type="text" name="reason" id="reason"></td><td><small>$txt_ban_optional</small></td></tr>
            	$banmessage_html
            	</table><br>
            	<small>$txt_ban_help</small>
            	<legend>
            	</fieldset>
            	</form><br>
            EOF;
    }

    private function manageBansTable(): string
    {
        $text = '';
        $allbans = $this->allBans();
        if (count($allbans) > 0) {
            $text .= '<table border="1"><tr><th>' . 'IP Address' . '</th><th>' . 'Set At' . '</th><th>' . 'Expires' . '</th><th>' . 'Reason' . '</th><th>&nbsp;</th></tr>';
            foreach ($allbans as $ban) {
                $expire = ($ban['expire'] > 0) ? $this->formatDate($ban['expire']) : 'Does not expire';
                $reason = ($ban['reason'] == '') ? '&nbsp;' : htmlentities($ban['reason']);
                $text .= '<tr><td>' . $ban['ip'] . '</td><td>' . $this->formatDate($ban['timestamp']) . '</td><td>' . $expire . '</td><td>' . $reason . '</td><td><a href="?manage&bans&lift=' . $ban['id'] . '">' . 'lift' . '</a></td></tr>';
            }
            $text .= '</table>';
        }
        return $text;
    }

    private function manageModeratePostForm(): string
    {
        $txt_moderate = 'Moderate a post';
        $txt_postid = 'Post ID';
        $txt_submit = 'Submit';
        $txt_tip = 'Tip';
        $txt_tiptext1 = 'While browsing the image board, you can easily moderate a post if you are logged in.';
        $txt_tiptext2 = 'Tick the box next to a post and click "Delete" at the bottom of the page with a blank password.';
        return <<<EOF
            	<form id="tinyib" name="tinyib" method="get" action="?">
            	<input type="hidden" name="manage" value="">
            	<fieldset>
            	<legend>$txt_moderate</legend>
            	<div valign="top"><label for="moderate">$txt_postid</label> <input type="text" name="moderate" id="moderate"> <input type="submit" value="$txt_submit" class="managebutton"></div><br>
            	<b>$txt_tip:</b> $txt_tiptext1<br>
            	$txt_tiptext2<br>
            	</fieldset>
            	</form><br>
            EOF;
    }

    private function manageModerateAll(array $post_ids, int $threads, int $replies, array $ips): string
    {

        $txt_moderate = sprintf('Moderate %d posts', count($post_ids));
        $txt_delete_all = 'Delete all';
        $txt_ban_all = 'Ban all';
        if ($threads == 1 && $replies == 1) {
            $delete_info = '1 thread and 1 reply will be deleted.';
        } elseif ($threads == 1) {
            $delete_info = sprintf('1 thread and %d replies will be deleted.', $replies);
        } elseif ($replies == 1) {
            $delete_info = sprintf('%d threads and 1 reply will be deleted.', $threads);
        } else {
            $delete_info = sprintf('%1$d threads and %2$d replies will be deleted.', $threads, $replies);
        }
        if (count($ips) == 1) {
            $ban_info = '1 IP address will be banned.';
        } else {
            $ban_info = sprintf('%d IP addresses will be banned.', count($ips));
        }
        $ban_disabled = 'disabled';
        if ($this->isadmin) {
            $ban_disabled = '';
        }
        $post_ids_quoted = htmlentities(implode(',', $post_ids), ENT_QUOTES);
        $ips_comma = implode(',', $ips);
        return <<<EOF
            <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr><td width="50%">
            &nbsp;
            </td><td width="50%">

            <fieldset>
            <legend>$txt_moderate</legend>
            <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr><td>
            &nbsp;
            </td><td valign="top">

            <form method="get" action="?">
            <input type="hidden" name="manage" value="">
            <input type="hidden" name="delete" value="{$post_ids_quoted}">
            <input type="submit" value="$txt_delete_all" class="managebutton">
            </form>

            </td><td><small>$delete_info</small></td></tr>
            <tr><td>
            &nbsp;
            </td><td valign="top">

            <form method="get" action="?">
            <input type="hidden" name="manage" value="">
            <input type="hidden" name="bans" value="{$ips_comma}">
            <input type="hidden" name="posts" value="{$post_ids_quoted}">
            <input type="submit" value="$txt_ban_all" class="managebutton" $ban_disabled>
            </form>

            </td><td><small>$ban_info</small></td></tr>
            </table>
            </fieldset>

            </td></tr>
            </table>
            EOF;

    }

    private function manageModeratePost(array $post, bool $compact = false): string
    {

        $ban = $this->banByIP($post['ip']);
        $ban_disabled = (!$ban && $this->isadmin) ? '' : ' disabled';
        if ($ban) {
            $ban_info = sprintf(' A ban record already exists for %s', $post['ip']);
        } else {
            if (!$this->isadmin) {
                $ban_info = 'Only an administrator may ban an IP address.';
            } else {
                $ban_info = sprintf('IP address: %s', $post['ip']);
            }
        }

        $thread_or_reply = ($post['parent'] == 0) ? 'Thread' : 'Reply';

        $delete_info = '';
        if ($post['parent'] == 0) {
            $allPosts = $this->postsInThreadByID($post['id']);
            if (count($allPosts) > 1) {
                if (count($allPosts) == 2) {
                    $delete_info = '1 reply will be deleted.';
                } else {
                    $delete_info = sprintf('%d replies will be deleted.', count($allPosts) - 1);
                }
            }
        } else {
            $delete_info = sprintf('Belongs to %s', $this->postLink('&gt;&gt;' . $post['id']));
        }

        $sticky_html = '';
        $lock_html = '';
        if ($post['parent'] == 0 && !$compact) {
            $sticky_set = $post['stickied'] == 1 ? '0' : '1';
            $sticky_unsticky = $post['stickied'] == 1 ? 'Un-sticky' : 'Sticky';
            $sticky_unsticky_help = $post['stickied'] == 1 ? 'Return this thread to a normal state.' : 'Keep this thread at the top of the board.';
            $sticky_html = <<<EOF
                	<tr><td>
                		<form method="get" action="?">
                		<input type="hidden" name="manage" value="">
                		<input type="hidden" name="sticky" value="{$post['id']}">
                		<input type="hidden" name="setsticky" value="$sticky_set">
                		<input type="submit" value="$sticky_unsticky" class="managebutton">
                		</form>
                	</td><td><small>$sticky_unsticky_help</small></td></tr>
                EOF;

            $lock_set = $post['locked'] == 1 ? '0' : '1';
            $lock_label = $post['locked'] == 1 ? 'Unlock' : 'Lock';
            $lock_help = $post['locked'] == 1 ? 'Allow replying to this thread.' : 'Disallow replying to this thread.';
            $lock_html = <<<EOF
                	<tr><td>
                		<form method="get" action="?">
                		<input type="hidden" name="manage" value="">
                		<input type="hidden" name="lock" value="{$post['id']}">
                		<input type="hidden" name="setlock" value="$lock_set">
                		<input type="submit" value="$lock_label" class="managebutton">
                		</form>
                	</td><td><small>$lock_help</small></td></tr>
                EOF;
        }
        $post_html = $this->buildPost($post, false);

        $txt_moderating = sprintf('Moderating No.%d', $post['id']);
        $txt_action = 'Action';
        if ($post['parent'] == 0) {
            $txt_delete = 'Delete thread';
        } else {
            $txt_delete = 'Delete reply';
        }
        $txt_ban = 'Ban poster';

        $report_html = '';
        $reports = $this->reportsByPost($post['id']);
        if ($this->config->report && count($reports) > 0 && !$compact) {
            $txt_clear_reports = 'Approve';
            $report_info = count($reports) . ' ' . $this->plural(count($reports), 'report', 'reports');
            $report_html = <<<EOF
                <tr><td>
                	
                <form method="get" action="?">
                <input type="hidden" name="manage" value="">
                <input type="hidden" name="clearreports" value="{$post['id']}">
                <input type="submit" value="$txt_clear_reports" class="managebutton">
                </form>

                </td><td><small>$report_info</small></td></tr>
                EOF;
        }
        return <<<EOF
            	<fieldset>
            	<legend>$txt_moderating</legend>
            	
            	<table border="0" cellspacing="0" cellpadding="0" width="100%">
            	<tr><td width="50%" valign="top">
            	
            	<fieldset>
            	<legend>$thread_or_reply</legend>	
            	$post_html
            	</fieldset>
            	
            	</td><td width="50%" valign="top">
            	
            	<fieldset>
            	<legend>$txt_action</legend>
            	
            	<table border="0" cellspacing="0" cellpadding="0" width="100%">
            	<tr><td>
            	
            	<form method="get" action="?">
            	<input type="hidden" name="manage" value="">
            	<input type="hidden" name="delete" value="{$post['id']}">
            	<input type="submit" value="$txt_delete" class="managebutton">
            	</form>
            	
            	</td><td><small>$delete_info</small></td></tr>
            	<tr><td>
            	
            	<form method="get" action="?">
            	<input type="hidden" name="manage" value="">
            	<input type="hidden" name="bans" value="{$post['ip']}">
            	<input type="hidden" name="posts" value="{$post['id']}">
            	<input type="submit" value="$txt_ban" class="managebutton" $ban_disabled>
            	</form>
            	
            	</td><td><small>$ban_info</small></td></tr>

            	$sticky_html
            	
            	$lock_html
            	
            	$report_html
            	
            	</table>
            	
            	</fieldset>

            	</td></tr>
            	</table>
            	
            	</fieldset>
            	<br>
            EOF;
    }

    private function manageEditKeyword(int $id): string
    {
        $id = (int) $id;

        $v_text = '';
        $v_action = '';
        $v_regexp_checked = '';
        if ($id > 0) {
            $keyword = $this->keywordByID($id);
            if (empty($keyword)) {
                $this->fancyDie("Sorry, there doesn't appear to be a keyword with that ID.");
            }
            $v_text = htmlentities($keyword['text'], ENT_QUOTES);
            $v_action = $keyword['action'];

            if (substr($v_text, 0, 7) == 'REGEXP:') {
                $v_regexp_checked = 'selected';
                $v_text = substr($v_text, 7);
            }
        }

        $txt_keyword = 'Keyword';
        $txt_keywords = 'Keywords';
        $txt_action = 'Action';
        $txt_submit = $id > 0 ? 'Update' : 'Add';

        $return = <<<EOF
            	<form id="tinyib" name="tinyib" method="post" action="?manage&keywords=$id">
            	<fieldset>
            	<legend>$txt_keywords</legend>
            	<table border="0">
            	<tr><td><label for="keyword">$txt_keyword</label></td><td><input type="text" name="text" id="text" value="$v_text"> <label for="regexp">&nbsp; <input type="checkbox" name="regexp" value="1" $v_regexp_checked> Regular expression</label></td></tr>
            	<tr><td><label for="action">$txt_action</label></td><td><select name="action">
            EOF;
        if ($this->config->report && $this->config->reqmod != 'all') {
            $return .= '<option value="report"' . ($v_action == 'report' ? ' selected' : '') . '>' . 'Report' . '</option>';
        }
        $return .= '<option value="delete"' . ($v_action == 'delete' ? ' selected' : '') . '>' . 'Delete' . '</option>';
        $return .= '<option value="hide"' . ($v_action == 'hide' ? ' selected' : '') . '>' . 'Hide until approved' . '</option>';
        $return .= '<option value="ban1h"' . ($v_action == 'ban1h' ? ' selected' : '') . '>' . 'Delete and ban for 1 hour' . '</option>';
        $return .= '<option value="ban1d"' . ($v_action == 'ban1d' ? ' selected' : '') . '>' . 'Delete and ban for 1 day' . '</option>';
        $return .= '<option value="ban2d"' . ($v_action == 'ban2d' ? ' selected' : '') . '>' . 'Delete and ban for 2 days' . '</option>';
        $return .= '<option value="ban1w"' . ($v_action == 'ban1w' ? ' selected' : '') . '>' . 'Delete and ban for 1 week' . '</option>';
        $return .= '<option value="ban2w"' . ($v_action == 'ban2w' ? ' selected' : '') . '>' . 'Delete and ban for 2 weeks' . '</option>';
        $return .= '<option value="ban1m"' . ($v_action == 'ban1m' ? ' selected' : '') . '>' . 'Delete and ban for 1 month' . '</option>';
        $return .= '<option value="ban0"' . ($v_action == 'ban0' ? ' selected' : '') . '>' . 'Delete and ban permanently' . '</option>';
        return $return . <<<EOF
            	</select></td></tr>
            	<tr><td>&nbsp;</td><td><input type="submit" value="$txt_submit" class="managebutton"></td></tr>
            	</table>
            	</fieldset>
            	</form><br>
            EOF;
    }

    private function manageKeywordsTable(): string
    {
        $text = '';
        $keywords = $this->allKeywords();
        if (count($keywords) > 0) {
            $text .= '<table border="1"><tr><th>' . 'Keyword' . '</th><th>' . 'Action' . '</th><th>&nbsp;</th></tr>';
            foreach ($keywords as $keyword) {
                $action = '';
                switch ($keyword['action']) {
                    case 'report':
                        $action = 'Report';
                        break;
                    case 'hide':
                        $action = 'Hide until approved';
                        break;
                    case 'delete':
                        $action = 'Delete';
                        break;
                    case 'ban0':
                        $action = 'Delete and ban permanently';
                        break;
                    case 'ban1h':
                        $action = 'Delete and ban for 1 hour';
                        break;
                    case 'ban1d':
                        $action = 'Delete and ban for 1 day';
                        break;
                    case 'ban2d':
                        $action = 'Delete and ban for 2 days';
                        break;
                    case 'ban1w':
                        $action = 'Delete and ban for 1 week';
                        break;
                    case 'ban2w':
                        $action = 'Delete and ban for 2 weeks';
                        break;
                    case 'ban1m':
                        $action = 'Delete and ban for 1 month';
                        break;
                }
                $text .= '<tr><td>' . htmlentities($keyword['text']) . '</td><td>' . $action . '</td><td><a href="?manage&keywords=' . $keyword['id'] . '">' . 'Edit' . '</a> <a href="?manage&keywords&deletekeyword=' . $keyword['id'] . '">' . 'Delete' . '</a></td></tr>';
            }
            $text .= '</table>';
        }
        return $text;
    }

    private function manageStatus(): string
    {

        $threads = $this->countThreads();
        $bans = count($this->allBans());
        $reports = $this->allReports();

        $info = $threads . ' ' . $this->plural($threads, 'thread', 'threads');
        if ($this->config->report) {
            $info .= ', ' . count($reports) . ' ' . $this->plural(count($reports), 'report', 'reports');
        }
        $info .= ', ' . $bans . ' ' . $this->plural($bans, 'ban', 'bans');

        $output = '';

        $reqmod_html = '';

        if ($this->config->reqmod == 'files' || $this->config->reqmod == 'all') {
            $reqmod_post_html = '';

            $reqmod_posts = $this->latestPosts(false);
            foreach ($reqmod_posts as $post) {
                if ($reqmod_post_html != '') {
                    $reqmod_post_html .= '<tr><td colspan="2"><hr></td></tr>';
                }
                $reqmod_post_html .= '<tr><td>' . $this->buildPost($post, false) . '</td><td valign="top" align="right">
			<table border="0"><tr><td>
			<form method="get" action="?"><input type="hidden" name="manage" value=""><input type="hidden" name="approve" value="' . $post['id'] . '"><input type="submit" value="' . 'Approve' . '" class="managebutton"></form>
			</td><td>
			<form method="get" action="?"><input type="hidden" name="manage" value=""><input type="hidden" name="moderate" value="' . $post['id'] . '"><input type="submit" value="' . 'More Info' . '" class="managebutton"></form>
			</td></tr><tr><td align="right" colspan="2">
			<form method="get" action="?"><input type="hidden" name="manage" value=""><input type="hidden" name="delete" value="' . $post['id'] . '"><input type="submit" value="' . 'Delete' . '" class="managebutton"></form>
			</td></tr></table>
			</td></tr>';
            }

            if ($reqmod_post_html != '') {
                $txt_pending = 'Pending posts';
                $reqmod_html = <<<EOF
                    	<fieldset>
                    	<legend>$txt_pending</legend>
                    	<table border="0" cellspacing="0" cellpadding="0" width="100%">
                    	$reqmod_post_html
                    	</table>
                    	</fieldset>
                    EOF;
            }
        }

        if ($this->config->report && !empty($reports)) {
            $status_html = $this->manageReportsPage('');
        } else {
            $posts = $this->latestPosts(true);
            $txt_recent_posts = 'Recent posts';

            $post_html = '';
            foreach ($posts as $post) {
                if ($post_html != '') {
                    $post_html .= '<tr><td colspan="2"><hr></td></tr>';
                }

                $post_html .= '<tr><td>' . $this->buildPost($post, false) . '</td><td valign="top" align="right"><form method="get" action="?"><input type="hidden" name="manage" value=""><input type="hidden" name="moderate" value="' . $post['id'] . '"><input type="submit" value="' . 'Moderate' . '" class="managebutton"></form></td></tr>';
            }

            $status_html = <<<EOF
                		<fieldset>
                		<legend>$txt_recent_posts</legend>
                		<table border="0" cellspacing="0" cellpadding="0" width="100%">
                			$post_html
                		</table>
                		</fieldset>
                EOF;
        }

        $txt_status = 'Status';
        $txt_info = 'Info';
        $output .= <<<EOF
            	<fieldset>
            	<legend>$txt_status</legend>
            	
            	<fieldset>
            	<legend>$txt_info</legend>
            	<table border="0" cellspacing="0" cellpadding="0" width="100%">
            	<tbody>
            	<tr><td>
            		$info
            	</td>
            	</tr>
            	</tbody>
            	</table>
            	</fieldset>

            	$reqmod_html
            	
            	$status_html
            	
            	</fieldset>
            	<br>
            EOF;

        return $output;
    }

    private function manageInfo(string $text): string
    {
        return '<div class="manageinfo">' . $text . '</div>';
    }

    private function encodeJson(array $array): string
    {
        return json_encode($array, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function buildSinglePostJson(array $post): array
    {
        $name = $post['name'];
        if ($name == '') {
            $name = 'Anonymous';
        }

        $output = ['id' => $post['id'], 'parent' => $post['parent'], 'timestamp' => $post['timestamp'], 'bumped' => $post['bumped'], 'name' => $name, 'tripcode' => $post['tripcode'], 'subject' => $post['subject'], 'message' => $post['message'], 'file' => $post['file'], 'file_hex' => $post['file_hex'], 'file_original' => $post['file_original'], 'file_size' => $post['file_size'], 'file_size_formated' => $post['file_size_formatted'], 'image_width' => $post['image_width'], 'image_height' => $post['image_height'], 'thumb' => $post['thumb'], 'thumb_width' => $post['thumb_width'], 'thumb_height' => $post['thumb_height']];

        if ($post['parent'] == 0) {
            $replies = count($this->postsInThreadByID($post['id'])) - 1;
            $images = $this->imagesInThreadByID($post['id']);

            $output = array_merge($output, ['stickied' => $post['stickied'], 'locked' => $post['locked'], 'replies' => $replies, 'images' => $images]);
        }

        return $output;
    }

    private function buildIndexJson(): string
    {
        $output = ['threads' => []];

        $threads = $this->allThreads();
        foreach ($threads as $thread) {
            array_push($output['threads'], ['id' => $thread['id'], 'subject' => $thread['subject'], 'bumped' => $thread['bumped']]);
        }

        return $this->encodeJson($output);
    }

    private function buildCatalogJson(): string
    {
        $output = ['threads' => []];

        $threads = $this->allThreads();
        foreach ($threads as $post) {
            array_push($output['threads'], $this->buildSinglePostJson($post));
        }

        return $this->encodeJson($output);
    }

    private function buildSingleThreadJson(int $id): string
    {
        $output = ['posts' => []];

        $posts = $this->postsInThreadByID($id);
        foreach ($posts as $post) {
            array_push($output['posts'], $this->buildSinglePostJson($post));
        }

        return $this->encodeJson($output);
    }

}
