## Resources Site Template

## Setup for Local Development

1. Clone the repository
2. Copy the .env.example file to .env
3. In the .env file add your database credentials and set `BRAND_ORG_NAME`. See `config/branding.php` for the full list of branding options including colours, logo, and images.
4. Create the local MySQL database if needed (e.g. `mysql -u root -e "CREATE DATABASE resources_site_template;"`)
5. Run `composer install` to install PHP dependencies
6. Run `npm install` to install JavaScript dependencies
7. Run `php artisan key:generate` to set the application key
8. Run `php artisan migrate --seed` to set up the database with tables and seed
9. Run `npm run dev` to set Vite running, which reloads assets on changes
10. If you're using Laravel Valet or Herd, go to `http://resources-site-template.test` in your browser. Otherwise, run `php artisan serve` and go to the provided URL.

If you've run the seeders, you can log in with test@example.com and password 'password'.

To seed example troves, collections, and tags for local development, run the example data seeder separately after the main seed:

```
php artisan db:seed --class="Database\Seeders\Example\ExampleDataSeeder"
```

### File Storage (AWS S3)

The app uses AWS S3 for file storage. Set up an S3 bucket and add your credentials to `.env` — see `.env.example` for the required `AWS_*` variables. It's recommended to use S3 locally too so your setup matches production.

If you don't have S3 set up, you can use the local public disk for development by setting `FILESYSTEM_DISK=local` and `MEDIA_DISK=public` in your `.env`, then running `php artisan storage:link`. Note that files won't persist in the same way as S3.

### Meilisearch

Search is powered by Meilisearch. If you don't need search locally, you can disable it by setting `SCOUT_DRIVER=null` in your `.env`.

To run Meilisearch locally:

1. Install Meilisearch - instructions [here](https://www.meilisearch.com/docs/learn/getting_started/installation).
2. Run `./meilisearch --master-key="aSampleMasterKey"` — keep this running in a separate terminal window.