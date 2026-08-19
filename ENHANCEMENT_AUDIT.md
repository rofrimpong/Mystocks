# CNMG STOCKS Enhancement Audit

## Existing foundations confirmed
- Laravel Product model with category, SKU/barcode, prices, unit, image path, minimum stock, inventory tracking, and SoftDeletes.
- Product create/read/update/delete API routes already existed. Delete already preserves historical records through soft delete.
- Inventory balances and immutable inventory movement ledger already existed.
- Opening stock and audited stock adjustment APIs already existed and record the acting user.
- Purchases already increase stock automatically through PurchaseService and support supplier, date, invoice/reference and unit cost.
- Sales already reduce stock and create inventory movement history.
- Supplier and category APIs already existed.
- Sanctum logout/logout-all APIs and frontend logout service already existed. Desktop had logout, mobile access was missing.
- User table already contained is_platform_admin and is_active fields, but no platform-admin UI/API existed.
- Businesses already had active/trial/suspended/cancelled statuses, but suspended status was not enforced by business middleware.

## Added in this enhanced source
- Full Add New Product form: name, SKU, barcode, category, unit, cost, selling price, opening quantity, reorder level, preferred supplier, description and image.
- Product edit UI and soft archive UI.
- Preferred supplier relation and migration.
- Product image upload endpoint using Laravel public storage.
- Inventory screen search, low-stock filter, out-of-stock badge, Restock, Adjust and History controls.
- Restock uses existing PurchaseService, so stock increases through the same accounting/inventory path as purchases.
- Stock history shows opening stock, purchase/restock, sales, adjustments, actor, reason/reference, date and current balance.
- Zero-balance tracked products are materialized for the selected branch so newly-created products can appear as Out of stock.
- Profile page and mobile-accessible Sign out.
- Platform Admin dashboard with counts, user suspend/activate, and business status moderation.
- Suspension enforcement for users and businesses.
- Artisan command: `php artisan app:make-platform-admin user@example.com`.
- CNMG STOCKS visible rebrand plus PWA icon assets.

## Deployment-impacting change
Run the new Laravel migration after uploading the backend changes:
`php artisan migrate --force`

Product images require the existing `public/storage` symlink to remain valid.
