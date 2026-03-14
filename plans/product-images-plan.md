# Product Images Plan for Outsourced Technologies

## Current Status

### Database Products (30 products need images)

| # | SKU | Product Name | Category |
|---|-----|--------------|----------|
| 1 | LAP-DELL-001 | Dell XPS 15 | Laptops |
| 2 | LAP-HP-001 | HP EliteBook 840 | Laptops |
| 3 | LAP-ASUS-001 | ASUS VivoBook 15 | Laptops |
| 4 | LAP-MAC-001 | Apple MacBook Air M2 | Laptops |
| 5 | LAP-LEN-001 | Lenovo ThinkPad E14 | Laptops |
| 6 | LAP-DELL-002 | Dell Inspiron 3520 | Laptops |
| 7 | NET-CISC-001 | Cisco Catalyst 2960-X | Networking |
| 8 | NET-TP-001 | TP-Link TL-SG1008D | Networking |
| 9 | NET-TP-002 | TP-Link Archer AX73 | Networking |
| 10 | NET-UBI-001 | Ubiquiti UniFi 6 Lite | Networking |
| 11 | NET-MIK-001 | MikroTik hAP ac3 | Networking |
| 12 | NET-TP-003 | TP-Link TL-SG1016D | Networking |
| 13 | NET-DLI-001 | D-Link DGS-1210-28 | Networking |
| 14 | NET-CISC-002 | Cisco RV340 | Networking |
| 15 | ACC-MOU-001 | Logitech MX Master 3S | Accessories |
| 16 | ACC-MOU-002 | HP X1000 Wired Mouse | Accessories |
| 17 | ACC-KB-001 | Logitech K380 Keyboard | Accessories |
| 18 | ACC-KB-002 | HP Keyboard 100 | Accessories |
| 19 | ACC-CAB-001 | Cat6 Ethernet Cable 10m | Accessories |
| 20 | ACC-CAB-002 | Cat6 Ethernet Cable 30m | Accessories |
| 21 | ACC-CHG-001 | Universal Laptop Charger 65W | Accessories |
| 22 | ACC-CHG-002 | Dell Charger 65W | Accessories |
| 23 | ACC-HUB-001 | USB-C Hub 7-in-1 | Accessories |
| 24 | ACC-HUB-002 | USB Hub 4-Port | Accessories |
| 25 | ACC-HEA-001 | Sony WH-1000XM5 | Accessories |
| 26 | ACC-WEB-001 | Logitech C920 HD Pro | Accessories |
| 27 | PHN-APP-001 | iPhone 14 | Phones & Tablets |
| 28 | PHN-SAM-001 | Samsung Galaxy S23 | Phones & Tablets |
| 29 | PHN-RED-001 | Redmi Note 12 | Phones & Tablets |
| 30 | TAB-APP-001 | iPad 10th Gen | Phones & Tablets |
| 31 | TAB-SAM-001 | Samsung Galaxy Tab A8 | Phones & Tablets |
| 32 | STR-SSD-001 | Samsung 980 PRO 1TB | Storage |
| 33 | STR-SSD-002 | Kingston A400 480GB | Storage |
| 34 | STR-HDD-001 | WD Blue 1TB | Storage |
| 35 | STR-USB-001 | SanDisk Ultra Flair 64GB | Storage |
| 36 | STR-USB-002 | Kingston DataTraveler 128GB | Storage |
| 37 | STR-SDC-001 | SanDisk Extreme 128GB | Storage |
| 38 | DES-DELL-001 | Dell OptiPlex 7090 | Desktops |
| 39 | DES-HP-001 | HP ProDesk 400 G7 | Desktops |
| 40 | PRT-HP-001 | HP LaserJet Pro MFP M428fdw | Printers |
| 41 | PRT-CAN-001 | Canon PIXMA G3010 | Printers |
| 42 | PRT-EP-001 | Epson L3250 | Printers |

### Current Image Status

- **Images Directory**: `assets/images/products/` - **DOES NOT EXIST** (needs to be created)
- **Database product_images table**: Empty (no images uploaded yet)
- **Admin Panel Image Upload**: NOT IMPLEMENTED (needs to be added)

---

## Recommended Image Sources

### Manufacturer Official Websites
Download product images from official manufacturer pages:

| Product Brand | Official Website |
|--------------|------------------|
| Dell | dell.com |
| HP | hp.com |
| Lenovo | lenovo.com |
| Apple | apple.com |
| ASUS | asus.com |
| Cisco | cisco.com |
| TP-Link | tp-link.com |
| Ubiquiti | ui.com |
| MikroTik | mikrotik.com |
| D-Link | dlink.com |
| Logitech | logitech.com |
| Sony | sony.com |
| Samsung | samsung.com |
| Apple (iPhone/iPad) | apple.com |
| Redmi | mi.com |
| SanDisk | westerndigital.com |
| Kingston | kingston.com |
| WD | westerndigital.com |
| Canon | canon.com |
| Epson | epson.com |

### Image Requirements
- **Format**: JPG or PNG
- **Recommended Size**: 800x800 pixels (square) or 1200x1200
- **File Naming**: Use product SKU or slug (e.g., `dell-xps-15.jpg`)
- **Location**: Store in `assets/images/products/`

---

## Implementation Plan

### Phase 1: Create Directory Structure
- [ ] Create `assets/images/products/` directory

### Phase 2: Add Image Upload to Admin Panel
- [ ] Modify `admin/products/add.php` to include image upload form
- [ ] Modify `admin/products/edit.php` to include image upload form
- [ ] Create PHP handler for image uploads
- [ ] Add image display in admin product list

### Phase 3: Download/Upload Product Images
- [ ] Download product images from manufacturer websites
- [ ] Upload images via admin panel OR directly to `assets/images/products/`
- [ ] Associate images with products in database

---

## Image Upload Implementation Details

### Database Changes Needed
The `product_images` table already exists. You need to:
1. Add image upload form to admin
2. Save uploaded files to `assets/images/products/`
3. Insert records into `product_images` table

### Suggested Filename Convention
```
{sku}.jpg
Examples:
- LAP-DELL-001.jpg
- NET-TP-002.jpg
- ACC-MOU-001.jpg
```

---

## Ready to Proceed?

To implement this plan, I need to:
1. **Switch to Code mode** to add image upload functionality to the admin panel
2. **Create the images directory** 
3. **Provide you with detailed download URLs** for each product

Would you like me to proceed with implementing the image upload functionality in the admin panel?
