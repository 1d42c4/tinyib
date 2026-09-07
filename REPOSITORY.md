# Repository access and recovery

This repository is intended to be a private, read-only snapshot of the modernized TinyIB source.

GitHub protection settings are configured separately from these files. A locked protected branch blocks normal updates, force pushes and branch deletion. If the account's plan does not support protection on private repositories, archiving the repository makes its code and branches read-only until the owner explicitly unarchives it.

Repository owners retain the ability to change protection, unarchive or delete the entire repository. No file in a Git repository can remove those owner privileges. Keep the separate local Git bundle as a recovery copy; it contains the committed source and history and can be cloned with `git clone path/to/tinyib-github-backup.bundle tinyib-restored`.

The working application remains separate from this source snapshot. Settings, databases, uploaded files and generated pages are intentionally excluded. `.gitignore` protects against accidentally adding these runtime files, while the blank `settings.default.php` documents setup.

The root and bundled-font license files retain the original copyright and license notices.
