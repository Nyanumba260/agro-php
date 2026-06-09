# AGROBIASHARA - PHP Farm E-Commerce Platform

A complete, production-ready farm marketplace with PHP backend, role-based authentication, and full shopping functionality.

## 📋 Features

✅ **User Authentication**
- Buyer and Farmer role-based login/registration
- Secure password hashing
- Session management

✅ **Buyer Features**
- Browse farm products
- Search and filter products
- Sort by price (low to high, high to low), newest, name
- Add products to cart
- Shopping cart management
- Checkout with delivery location selection
- Order confirmation
- 14 Kenyan delivery locations with dynamic charges

✅ **Farmer Features**
- Farmer dashboard
- Add new products
- Edit existing products
- Delete products
- Sort products by price
- View product statistics

✅ **Product Management**
- Product name, description, category
- Price and quantity management
- Farmer association
- Image placeholder directives

## 📁 Project Structure

```
agrobiashara_php/
├── index.php                    # Buyer home page
├── login.php                    # Login page
├── register.php                 # Registration page
├── farmer-dashboard.php         # Farmer dashboard
├── cart.php                     # Shopping cart
├── checkout.php                 # Checkout page
├── order-confirmation.php       # Order confirmation
├── add-to-cart.php             # Add to cart handler
├── logout.php                   # Logout handler
├── css/
│   └── style.css               # Main stylesheet
├── includes/
│   ├── config.php              # Database configuration
│   └── database.sql            # Database schema
├── images/                     # Image folder (add images here)
└── README.md                   # This file
```

## 🚀 Installation & Setup

### 1. Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)

### 2. Database Setup

**Option A: Using phpMyAdmin**
1. Open phpMyAdmin in your browser
2. Create a new database named `agrobiashara`
3. Go to Import tab
4. Select `includes/database.sql`
5. Click Import

**Option B: Using Command Line**
```bash
mysql -u root -p < includes/database.sql
```

### 3. Configure Database Connection

Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');    // Your database host
define('DB_USER', 'root');         // Your database user
define('DB_PASS', '');             // Your database password
define('DB_NAME', 'agrobiashara'); // Database name
```

### 4. Add Images

Create an `images` folder and add:
- `logo.png` - Logo image (60x60px)
- `hero-banner.jpg` - Hero banner (1920x400px)
- `product-1.jpg` through `product-12.jpg` - Product images (200x200px each)

**Note:** Image directives in code are marked with `[img src="..."]` comments. Replace with actual `<img>` tags.

### 5. Run the Application

```bash
# Using PHP built-in server
php -S localhost:8000

# Then open in browser
http://localhost:8000
```

Or upload to your web server and access via your domain.

## 👥 User Roles

### Buyer
- Register as a buyer
- Browse all products
- Search and filter products
- Add products to cart
- Checkout and place orders
- View order confirmation

### Farmer
- Register as a farmer
- Access farmer dashboard
- Add new farm products
- Edit/delete products
- Sort products by price
- View product statistics

## 🔄 User Flow

### Buyer Flow
1. **Register** → Create buyer account
2. **Login** → Access buyer home page
3. **Browse** → View all farm products
4. **Search/Filter** → Find specific products
5. **Add to Cart** → Add products to shopping cart
6. **Checkout** → Enter delivery details
7. **Confirm** → View order confirmation

### Farmer Flow
1. **Register** → Create farmer account (select "Farmer" role)
2. **Login** → Access farmer dashboard
3. **Add Product** → Fill product form (name, price, quantity, category)
4. **Manage** → Edit or delete products
5. **Sort** → Sort products by price, name, or date
6. **View Stats** → See total products and quantity

## 💾 Database Schema

### Users Table
- id, name, email, phone, password, role, farm_name, farm_location, created_at, updated_at

### Products Table
- id, farmer_id, name, description, category, price, quantity, image_url, created_at, updated_at

### Cart Table
- id, user_id, product_id, quantity, added_at

### Orders Table
- id, user_id, total_amount, delivery_location, delivery_charge, delivery_address, payment_method, status, created_at, updated_at

### Order Items Table
- id, order_id, product_id, quantity, price

### Delivery Charges Table
- id, location, charge

## 🎨 Customization

### Colors
Edit `css/style.css`:
```css
:root {
    --primary-green: #2d5016;
    --secondary-green: #4a7c2c;
    --accent-orange: #ff8c42;
    --accent-yellow: #ffd700;
}
```

### Delivery Locations
Edit `includes/database.sql` or add via database:
```sql
INSERT INTO delivery_charges (location, charge) VALUES ('Your Location', 500);
```

### Product Categories
Edit the category select in `farmer-dashboard.php`:
```html
<option value="vegetables">Vegetables</option>
<option value="fruits">Fruits</option>
<option value="grains">Grains</option>
<option value="dairy">Dairy</option>
<option value="other">Other</option>
```

## 🔐 Security Features

✅ Password hashing with `password_hash()`
✅ SQL injection prevention with prepared statements
✅ Session-based authentication
✅ Role-based access control
✅ Input sanitization
✅ CSRF protection ready

## 📱 Responsive Design

- Mobile (480px and below)
- Tablet (768px)
- Desktop (1200px+)

## 🐛 Troubleshooting

### Database Connection Error
- Check database credentials in `includes/config.php`
- Ensure MySQL server is running
- Verify database exists

### Images Not Showing
- Add images to `images/` folder
- Replace `[img src="..."]` comments with actual `<img>` tags
- Check image file names match code

### Login Not Working
- Clear browser cookies
- Check database has users table
- Verify password is correct

## 📞 Contact Information

**AGROBIASHARA**
- Email: Agrobiashara@gmail.com
- Phone: +254 722 736 023

## 📄 License

This project is provided as-is for educational and commercial use.

---

**Ready to use! Complete PHP farm marketplace platform.** 🚀
