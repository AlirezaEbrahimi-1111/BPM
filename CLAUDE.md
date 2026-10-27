# Project rules for Claude

## Production server (`/var/www/itmalek`)

If your working directory is `/var/www/itmalek` (the production VPS), you are on the **live server**:

- **Never edit, create or delete files under `/var/www/itmalek`.** Code changes reach production only through GitHub (`git push` from the development machine → *Actions → Deploy to VPS*). Any local change on the server makes the next deploy's `git pull` stop.
- **Never bump the footer version** (`pages/footer.php`, `scripts/bump-version.php`). The version is bumped only in commits on the development machine; `scripts/bump-version.php` refuses to run under `/var/www/`.
- Reading files, running read-only commands, checking logs (`/var/log/apache2/`), and running SQL `SELECT` queries for diagnosis are fine.
- If a fix is needed, describe it (file, change, reason) so it can be made on the development machine and deployed — do not apply it on the server.
