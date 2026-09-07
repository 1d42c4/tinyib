<?php

declare(strict_types=1);

namespace TinyIB;

trait Storage
{
    private function initializeAccounts(): void
    {
        if ($this->database->count('SELECT COUNT(*) FROM ' . $this->table('accounts')) !== 0) {
            return;
        }
        if ($this->config->adminpass === '') {
            throw new BoardMessage('Set adminpass in settings.php for the first run, then clear it after the account is created.');
        }
        $lock = $this->lockDatabase();
        if ($this->database->count('SELECT COUNT(*) FROM ' . $this->table('accounts')) === 0) {
            $this->insertAccount(['username' => 'admin', 'password' => $this->config->adminpass, 'role' => Role::SuperAdministrator->value]);
            if ($this->config->modpass !== '') {
                $this->insertAccount(['username' => 'mod', 'password' => $this->config->modpass, 'role' => Role::Moderator->value]);
            }
        }
    }

    private function table(string $kind): string
    {
        return $this->database->identifier($this->config->{'db' . $kind});
    }

    private function accountByID(int $id): array
    {
        return $this->database->row('SELECT * FROM ' . $this->table('accounts') . ' WHERE id = ?', [$id]);
    }
    private function accountByUsername(string $username): array
    {
        return $this->database->row('SELECT * FROM ' . $this->table('accounts') . ' WHERE username = ?', [$username]);
    }
    private function allAccounts(): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('accounts') . ' ORDER BY role, username');
    }

    private function insertAccount(array $account): int
    {
        if ($this->accountByUsername($account['username']) !== []) {
            throw new BoardMessage('That username is already in use.');
        }
        return $this->database->insert($this->config->dbaccounts, [
            'username' => $account['username'],
            'password' => Passwords::hash($account['password']),
            'role' => $account['role'],
            'lastactive' => 0,
        ]);
    }

    private function updateAccount(array $account): void
    {
        $previous = $this->accountByID((int) $account['id']);
        $password = $account['password'] === $previous['password'] ? $account['password'] : Passwords::hash($account['password']);
        $this->database->update($this->config->dbaccounts, (int) $account['id'], [
            'username' => $account['username'], 'password' => $password, 'role' => (int) $account['role'], 'lastactive' => (int) $account['lastactive'],
        ]);
    }

    private function deleteAccountByID(int $id): void
    {
        $this->database->execute('DELETE FROM ' . $this->table('accounts') . ' WHERE id = ?', [$id]);
    }
    private function banByID(int $id): array
    {
        return $this->database->row('SELECT * FROM ' . $this->table('bans') . ' WHERE id = ?', [$id]);
    }
    private function banByIP(string $ip): array
    {
        return $this->database->row('SELECT * FROM ' . $this->table('bans') . ' WHERE ip = ? OR ip = ?', [$ip, $this->hashData($ip)]);
    }
    private function allBans(): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('bans') . ' ORDER BY timestamp DESC');
    }
    private function insertBan(array $ban): int
    {
        return $this->database->insert($this->config->dbbans, ['ip' => $this->hashData($ban['ip']), 'timestamp' => time(), 'expire' => (int) $ban['expire'], 'reason' => $ban['reason']]);
    }
    private function clearExpiredBans(): void
    {
        $this->database->execute('DELETE FROM ' . $this->table('bans') . ' WHERE expire > 0 AND expire <= ?', [time()]);
    }
    private function deleteBanByID(int $id): void
    {
        $this->database->execute('DELETE FROM ' . $this->table('bans') . ' WHERE id = ?', [$id]);
    }

    private function keywordByID(int $id): array
    {
        return $this->database->row('SELECT * FROM ' . $this->table('keywords') . ' WHERE id = ?', [$id]);
    }
    private function keywordByText(string $text): array
    {
        return $this->database->row('SELECT * FROM ' . $this->table('keywords') . ' WHERE text = ?', [mb_strtolower($text)]);
    }
    private function allKeywords(): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('keywords') . ' ORDER BY text');
    }
    private function insertKeyword(array $keyword): void
    {
        $this->database->insert($this->config->dbkeywords, ['text' => mb_strtolower($keyword['text']), 'action' => $keyword['action']]);
    }
    private function deleteKeyword(int $id): void
    {
        $this->database->execute('DELETE FROM ' . $this->table('keywords') . ' WHERE id = ?', [$id]);
    }

    private function getLogs(int $offset, int $limit): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('logs') . ' ORDER BY timestamp DESC, id DESC LIMIT ? OFFSET ?', [max(0, $limit), max(0, $offset)]);
    }
    private function allLogs(): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('logs') . ' ORDER BY timestamp, id');
    }
    private function insertLog(array $log): void
    {
        $this->database->insert($this->config->dblogs, ['timestamp' => (int) $log['timestamp'], 'account' => (int) $log['account'], 'message' => $log['message']]);
    }

    private function uniquePosts(): int
    {
        return $this->database->count('SELECT COUNT(DISTINCT ip) FROM ' . $this->table('posts'));
    }
    private function postByID(int $id): array
    {
        return $this->database->row('SELECT * FROM ' . $this->table('posts') . ' WHERE id = ?', [$id]);
    }
    private function threadExistsByID(int $id): bool
    {
        return $this->database->count('SELECT COUNT(*) FROM ' . $this->table('posts') . ' WHERE id = ? AND parent = 0', [$id]) > 0;
    }

    private function insertPost(array $post): int
    {
        unset($post['id']);
        $post['timestamp'] = time();
        $post['bumped'] = $post['timestamp'];
        $post['ip'] = $this->hashData($this->remoteAddress());
        return $this->database->insert($this->config->dbposts, $post);
    }

    private function updatePostMessage(int $id, string $message): void
    {
        $this->database->update($this->config->dbposts, $id, ['message' => $message]);
    }
    private function updatePostBumped(int $id, int $bumped): void
    {
        $this->database->update($this->config->dbposts, $id, ['bumped' => $bumped]);
    }
    private function approvePostByID(int $id, int $moderated): void
    {
        $this->database->update($this->config->dbposts, $id, ['moderated' => $moderated]);
    }
    private function bumpThreadByID(int $id): void
    {
        $this->updatePostBumped($id, time());
    }
    private function stickyThreadByID(int $id, int $setsticky): void
    {
        $this->database->update($this->config->dbposts, $id, ['stickied' => $setsticky]);
    }
    private function lockThreadByID(int $id, int $setlock): void
    {
        $this->database->update($this->config->dbposts, $id, ['locked' => $setlock]);
    }
    private function countThreads(): int
    {
        return $this->database->count('SELECT COUNT(*) FROM ' . $this->table('posts') . ' WHERE parent = 0 AND moderated > 0');
    }
    private function allThreads(bool $moderated_only = true): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('posts') . ' WHERE parent = 0' . ($moderated_only ? ' AND moderated > 0' : '') . ' ORDER BY stickied DESC, bumped DESC, id DESC');
    }
    private function numRepliesToThreadByID(int $id): int
    {
        return $this->database->count('SELECT COUNT(*) FROM ' . $this->table('posts') . ' WHERE parent = ? AND moderated > 0', [$id]);
    }
    private function postsInThreadByID(int $id, bool $moderated_only = true): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('posts') . ' WHERE (id = ? OR parent = ?)' . ($moderated_only ? ' AND moderated > 0' : '') . ' ORDER BY id', [$id,$id]);
    }

    private function imagesInThreadByID(int $id, bool $moderated_only = true): int
    {
        return $this->database->count('SELECT COUNT(*) FROM ' . $this->table('posts') . " WHERE (id = ? OR parent = ?) AND file <> ''" . ($moderated_only ? ' AND moderated > 0' : ''), [$id,$id]);
    }
    private function postsByHex(string $hex): array
    {
        return $this->database->rows('SELECT id, parent FROM ' . $this->table('posts') . ' WHERE file_hex = ?', [$hex]);
    }
    private function latestPosts(bool $moderated = true): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('posts') . ' WHERE moderated ' . ($moderated ? '>' : '=') . ' 0 ORDER BY id DESC LIMIT 10');
    }
    private function deletePostByID(int $id): void
    {
        $this->database->execute('DELETE FROM ' . $this->table('posts') . ' WHERE id = ?', [$id]);
    }

    private function trimThreads(): void
    {
        if ($this->config->maxthreads <= 0) {
            return;
        }
        foreach (array_slice($this->allThreads(), $this->config->maxthreads) as $post) {
            $this->deletePost($post['id']);
        }
    }

    private function lastPostByIP(): array
    {
        return $this->database->row('SELECT * FROM ' . $this->table('posts') . ' WHERE ip = ? OR ip = ? ORDER BY id DESC LIMIT 1', [$this->remoteAddress(),$this->hashData($this->remoteAddress())]);
    }
    private function reportByIP(int $post, string $ip): array
    {
        return $this->database->row('SELECT * FROM ' . $this->table('reports') . ' WHERE post = ? AND (ip = ? OR ip = ?)', [$post,$ip,$this->hashData($ip)]);
    }
    private function reportsByPost(int $post): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('reports') . ' WHERE post = ?', [$post]);
    }
    private function allReports(): array
    {
        return $this->database->rows('SELECT * FROM ' . $this->table('reports') . ' ORDER BY post');
    }
    private function insertReport(array $report): void
    {
        $this->database->insert($this->config->dbreports, ['ip' => $this->hashData($report['ip']), 'post' => (int) $report['post']]);
    }
    private function deleteReportsByPost(int $post): void
    {
        $this->database->execute('DELETE FROM ' . $this->table('reports') . ' WHERE post = ?', [$post]);
    }
    private function deleteReportsByIP(string $ip): void
    {
        $this->database->execute('DELETE FROM ' . $this->table('reports') . ' WHERE ip = ? OR ip = ?', [$ip,$this->hashData($ip)]);
    }
}
