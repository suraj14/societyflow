# SocietyFlow - Complete SaaS Society & Apartment Management System

SocietyFlow is a comprehensive, production-ready SaaS application for managing societies and apartment complexes. Built with Laravel 11, Livewire, and Tailwind CSS, it provides a complete solution for society management with multi-tenant architecture.

## 🚀 Features

### Multi-Tenant SaaS Architecture
- **Super Admin Dashboard**: Manage multiple societies, subscriptions, and system settings
- **Society-wise Data Isolation**: Complete data separation between societies
- **Subscription Management**: Multiple plans with billing cycles
- **Role-based Access Control**: Granular permissions for different user types

### Core Modules

#### Society Management
- Society profile and settings management
- Building and flat management
- Resident management (owners and tenants)
- Staff management with attendance tracking

#### Financial Management
- Automated maintenance bill generation
- Online payment processing (Razorpay/Stripe ready)
- Expense tracking and categorization
- Comprehensive financial reports

#### Communication & Complaints
- Notice board with targeted announcements
- Complaint management with status tracking
- Email and SMS notifications
- Resident communication tools

#### Facility & Visitor Management
- Facility booking system with approval workflow
- Visitor management with pre-approval
- Security integration for entry/exit tracking
- Comprehensive visitor logs

#### Reports & Analytics
- Collection reports
- Outstanding dues tracking
- Complaint analytics
- Visitor reports
- Staff attendance reports

## 🛠 Tech Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Blade Templates + Livewire 3
- **UI Framework**: Tailwind CSS
- **Database**: MySQL 8.0+
- **Authentication**: Laravel Breeze
- **Permissions**: Spatie Laravel Permission
- **File Storage**: Laravel Storage
- **PDF Generation**: DomPDF
- **Excel Export**: Maatwebsite Excel

## 📋 Requirements

- PHP 8.2 or higher
- MySQL 8.0 or higher
- Composer
- Node.js & NPM
- Web server (Apache/Nginx)

### PHP Extensions Required
- OpenSSL
- PDO
- Mbstring
- Tokenizer
- XML
- Ctype
- JSON
- BCMath
- Fileinfo
- GD
- cURL

## 🚀 Installation

### Method 1: Quick Installation (Recommended)

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-repo/societyflow.git
   cd societyflow
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Build assets**
   ```bash
   npm run build
   ```

5. **Run the installation wizard**
   ```bash
   php artisan serve
   ```
   Visit `http://localhost:8000/install` and follow the installation wizard.

### Method 2: Manual Installation

1. **Database Configuration**
   Update your `.env` file with database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=societyflow
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

2. **Run migrations and seeders**
   ```bash
   php artisan migrate:fresh --seed
   ```

3. **Create storage link**
   ```bash
   php artisan storage:link
   ```

4. **Set permissions**
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

## 🔧 Configuration

### Payment Gateways

#### Razorpay Setup
```env
RAZORPAY_KEY=your_razorpay_key
RAZORPAY_SECRET=your_razorpay_secret
```

#### Stripe Setup
```env
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
```

### Email Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
```

### SMS Configuration
```env
SMS_PROVIDER=your_sms_provider
SMS_API_KEY=your_sms_api_key
SMS_SENDER_ID=your_sender_id
```

## 👥 Default Users

After installation, you can login with:

**Super Admin**
- Email: `admin@societyflow.com`
- Password: `password`

## 🏗 Project Structure

```
societyflow/
├── app/
│   ├── Http/Controllers/
│   │   ├── SuperAdmin/          # Super admin controllers
│   │   ├── Society/             # Society admin controllers
│   │   ├── Resident/            # Resident controllers
│   │   └── Staff/               # Staff controllers
│   ├── Models/                  # Eloquent models
│   ├── Livewire/               # Livewire components
│   └── Services/               # Business logic services
├── database/
│   ├── migrations/             # Database migrations
│   └── seeders/               # Database seeders
├── resources/
│   ├── views/                 # Blade templates
│   ├── css/                   # Stylesheets
│   └── js/                    # JavaScript files
└── routes/
    └── web.php                # Application routes
```

## 🔐 Security Features

- CSRF protection on all forms
- SQL injection prevention
- XSS protection
- Secure password hashing
- Role-based access control
- Activity logging
- Session management

## 📱 Responsive Design

SocietyFlow is fully responsive and works seamlessly on:
- Desktop computers
- Tablets
- Mobile phones

## 🧪 Testing

Run the test suite:
```bash
php artisan test
```

## 📊 Performance

- Optimized database queries with proper indexing
- Lazy loading for relationships
- Caching for frequently accessed data
- Efficient pagination
- Image optimization

## 🔄 Updates & Maintenance

### Regular Maintenance
```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Database Backup
```bash
# Create backup
mysqldump -u username -p societyflow > backup.sql

# Restore backup
mysql -u username -p societyflow < backup.sql
```

## 🚀 Deployment

### Production Deployment

1. **Server Requirements**
   - PHP 8.2+ with required extensions
   - MySQL 8.0+
   - Web server (Apache/Nginx)
   - SSL certificate (recommended)

2. **Environment Configuration**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   ```

3. **Optimize for Production**
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm run build
   ```

4. **Set Proper Permissions**
   ```bash
   chmod -R 755 /path/to/societyflow
   chmod -R 775 storage bootstrap/cache
   ```

### Docker Deployment

A `docker-compose.yml` file is included for easy Docker deployment:

```bash
docker-compose up -d
```

## 📖 Documentation

Detailed documentation is available in the `/docs` directory:

- [User Manual](docs/user-manual.md)
- [Admin Guide](docs/admin-guide.md)
- [API Documentation](docs/api.md)
- [Customization Guide](docs/customization.md)

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new features
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:

- **Email**: support@societyflow.com
- **Documentation**: [docs.societyflow.com](https://docs.societyflow.com)
- **Issues**: [GitHub Issues](https://github.com/your-repo/societyflow/issues)

## 🎯 Roadmap

### Upcoming Features
- [ ] Mobile app (React Native)
- [ ] Advanced analytics dashboard
- [ ] Integration with accounting software
- [ ] Multi-language support
- [ ] Advanced reporting with charts
- [ ] Bulk SMS/Email campaigns
- [ ] Document management system
- [ ] Online meeting integration

### Version History
- **v1.0.0** - Initial release with core features
- **v1.1.0** - Enhanced reporting and analytics
- **v1.2.0** - Mobile responsiveness improvements
- **v2.0.0** - Multi-tenant architecture (Current)

## 🏆 Credits

SocietyFlow is built with love using these amazing open-source projects:

- [Laravel](https://laravel.com) - The PHP Framework
- [Livewire](https://laravel-livewire.com) - Full-stack framework for Laravel
- [Tailwind CSS](https://tailwindcss.com) - Utility-first CSS framework
- [Alpine.js](https://alpinejs.dev) - Lightweight JavaScript framework
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) - Permission management

---

**SocietyFlow** - Making society management simple and efficient! 🏢✨