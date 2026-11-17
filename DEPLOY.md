# Deploying CheckPrice to checkprice.ejikepius.dev (DirectAdmin)

1. **Subdomain Management → Add Subdomain**: `checkprice`, Document Root **Default** (`/domains/checkprice.ejikepius.dev/public_html`).
2. **File Manager → `/domains/checkprice.ejikepius.dev/`**: delete the empty `public_html` DirectAdmin created, upload `checkprice-deploy.zip` **here** and **Extract**. You should see `public_html`, `src`, `views`, `bin`, `data` side by side. Delete the zip.
3. **PHP 8.1+** (8.3 recommended) with `pdo_sqlite`, `gd` and `mbstring`. The `data` folder must be writable (755).
4. **Cron Jobs → Create** (daily price alerts, 8am): Minute `0`, Hour `8`, others `*`, command:
   ```
   /usr/local/bin/php /home/ejikepiu/domains/checkprice.ejikepius.dev/bin/alerts.php >/dev/null 2>&1
   ```
5. **Emails**: copy `config.local.example.php` to `config.local.php` (next to `src`) and add the mailbox password.
6. **Check it**: sign up, post a price with a photo, open the verdict link in a private window, try the basket.
