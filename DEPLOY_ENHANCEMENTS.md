# Deploy CNMG STOCKS Enhancements

This update does not require rebuilding the cPanel/Laravel hosting configuration from scratch.

1. Back up the current database and deployed source.
2. Upload/replace the changed backend and frontend source files.
3. From the Laravel backend directory run: `php artisan migrate --force`.
4. Keep the existing `public/storage` symlink; product images are stored on the public disk.
5. Build the frontend in your normal build environment and copy the generated `dist` files into Laravel `backend/public` as before.
6. If no platform administrator exists yet, run: `php artisan app:make-platform-admin YOUR_EMAIL`.
7. Log in again so the refreshed user object contains the platform-admin flag; the Admin menu will then appear.

Do not rename the `mystocks_*` localStorage keys or the production API hostname during this deployment. They are technical identifiers, not visible branding.
