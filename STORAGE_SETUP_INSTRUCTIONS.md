# Storage Setup Instructions for Live Server

## Problem
Images are not displaying because the `public/storage` symlink is not created, and the storage directories don't exist.

## Solution - Run These Commands in cPanel Terminal

Execute these commands one by one in your cPanel Terminal (WHM interface):

```bash
# 1. Remove old symlink if it exists
rm -rf /home/burgersoftwares/public_html/societyflow-app/public/storage

# 2. Create storage directories
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/notices
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/facilities
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/visitor-photos
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/avatars
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/society-logos
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/society-favicons
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/utility-bills
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/utility-bills/payments
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/service-providers
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/landing/features
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/landing/reviews
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/owners/documents
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/complaints
mkdir -p /home/burgersoftwares/public_html/societyflow-app/storage/app/public/complaint-updates

# 3. Create symlink from public/storage to storage/app/public
ln -s /home/burgersoftwares/public_html/societyflow-app/storage/app/public /home/burgersoftwares/public_html/societyflow-app/public/storage

# 4. Set proper permissions
chmod -R 755 /home/burgersoftwares/public_html/societyflow-app/storage
chmod -R 755 /home/burgersoftwares/public_html/societyflow-app/storage/app/public
chmod -R 755 /home/burgersoftwares/public_html/societyflow-app/public/storage

# 5. Verify symlink was created
ls -la /home/burgersoftwares/public_html/societyflow-app/public/storage
```

## Expected Output
After running command #5, you should see:
```
lrwxrwxrwx ... storage -> /home/burgersoftwares/public_html/societyflow-app/storage/app/public
```

## After Setup
1. Try creating a new notice with an image
2. Verify the image displays correctly
3. Try updating and deleting notices with images

## Troubleshooting
- If symlink creation fails, check that you have write permissions to `/home/burgersoftwares/public_html/societyflow-app/`
- If images still don't show, verify the symlink exists: `ls -la public/storage`
- Check file permissions: `ls -la storage/app/public/`
