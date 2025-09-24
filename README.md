# OM Requestor - Vehicle Maintenance Management System

A comprehensive web-based vehicle maintenance quotation and approval system built for OM Engineers and Sammaan Foundation.

## Features

### 🎯 Role-Based Access Control
- **Admin (OM Engineers)**: Complete system management, user creation, quotation generation
- **Requestor**: Vehicle registration and service request submission
- **Approver**: Quotation review and approval workflow

### 🚗 Vehicle Management
- Vehicle registration with validation
- Service request submission with detailed problem descriptions
- Request history and status tracking

### 💰 Quotation System
- Admin-generated quotations for service requests
- Direct quotation submission to approvers
- Amount tracking and work description management

### ✅ Approval Workflow
- Approver dashboard with pending quotations
- Bulk approval/rejection capabilities
- Notes and decision tracking
- Automated status updates

### 📊 Comprehensive Reporting
- Service requests reports with filtering
- Quotation reports with financial summaries
- Approval reports for approvers
- Financial reports with payment tracking
- PDF/HTML export functionality

### 📧 Email Notifications
- New request notifications to admin
- Quotation approval notifications
- Approval decision updates to requestors
- Work completion notifications

### 🎨 Modern UI/UX
- Google Material Design 3 implementation
- Mobile-first responsive design
- Clean, professional interface
- Intuitive navigation and workflows

## Technical Stack

- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3 (Material Design 3), Vanilla JavaScript
- **Email**: PHP mail() with HTML templates
- **Reports**: Custom PDF/HTML generation

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- SMTP server for email functionality

### Setup Steps

1. **Clone/Download the project**
   ```bash
   git clone <repository-url>
   cd om-requestor
   ```

2. **Database Setup**
   - Create a MySQL database named `om_requestor`
   - Import the database schema:
   ```bash
   mysql -u your_username -p om_requestor < database/schema.sql
   ```

3. **Configuration**
   - Edit `includes/config.php` with your database credentials
   - Update email settings in the config file:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'om_requestor');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');

   define('SMTP_HOST', 'your_smtp_host');
   define('SMTP_USERNAME', 'admin@om-engineers.org');
   define('SMTP_PASSWORD', 'your_email_password');
   ```

4. **File Permissions**
   - Ensure web server has read/write access to the project directory
   - Set appropriate permissions for uploads (if implemented)

5. **Default Login**
   - Username: `admin`
   - Password: `admin123`
   - Email: `admin@om-engineers.org`

### File Structure
```
om-requestor/
├── index.php                 # Login page
├── dashboard/                # Role-specific dashboards
│   ├── admin.php            # Admin dashboard
│   ├── requestor.php        # Requestor dashboard
│   ├── approver.php         # Approver dashboard
│   ├── users.php            # User management
│   ├── quotations.php       # Quotation management
│   └── reports.php          # Report generation
├── api/                     # REST API endpoints
│   ├── auth.php             # Authentication
│   ├── users.php            # User management API
│   ├── vehicles.php         # Vehicle management API
│   ├── requests.php         # Service request API
│   ├── quotations.php       # Quotation API
│   └── approvals.php        # Approval API
├── assets/                  # Static assets
│   ├── css/
│   │   ├── material.css     # Material Design 3 framework
│   │   └── style.css        # Custom styles
│   └── js/
│       ├── material.js      # Material Design 3 components
│       └── main.js          # Application JavaScript
├── includes/                # PHP includes
│   ├── config.php           # Configuration
│   ├── database.php         # Database connection
│   ├── functions.php        # Utility functions
│   └── email.php            # Email service
├── reports/                 # Report generation
│   └── pdf_generator.php    # PDF/HTML report generator
├── database/                # Database files
│   └── schema.sql           # Database schema
└── README.md               # This file
```

## Usage

### For Administrators (OM Engineers)
1. Login with admin credentials
2. Create user accounts for requestors and approvers
3. View and manage all service requests
4. Generate quotations for pending requests
5. Send quotations to appropriate approvers
6. Generate comprehensive reports

### For Requestors
1. Add vehicles with registration numbers
2. Submit service requests with detailed problem descriptions
3. Track request status and history
4. View quotation approvals and decisions

### For Approvers
1. Review pending quotations
2. Approve or reject quotations with notes
3. Use bulk approval features for efficiency
4. Generate approval reports

## Database Schema

### Key Tables
- `organizations`: Multi-organization support
- `users`: User accounts with role-based access
- `vehicles`: Vehicle registration and ownership
- `service_requests`: Service request submissions
- `quotations`: Generated quotations with amounts
- `approvals`: Approval workflow and decisions
- `work_orders`: Work completion and billing

### Relationships
- Organizations → Users (1:N)
- Users → Vehicles (1:N)
- Vehicles → Service Requests (1:N)
- Service Requests → Quotations (1:1)
- Quotations → Approvals (1:1)
- Quotations → Work Orders (1:1)

## Security Features

- CSRF protection on all forms
- Password hashing with PHP's password_hash()
- SQL injection prevention with prepared statements
- Role-based access control
- Session management and timeout
- Input validation and sanitization

## Customization

### Adding New Roles
1. Update database enum values in `users` table
2. Add role checks in `includes/functions.php`
3. Create role-specific dashboard pages
4. Update navigation and permissions

### Email Templates
- Modify templates in `includes/email.php`
- Customize styling and branding
- Add new notification types as needed

### Reporting
- Extend `reports/pdf_generator.php` for new report types
- Add filters and export options
- Customize report layouts and styling

## Production Deployment

### Security Checklist
- [ ] Change default admin password
- [ ] Update database credentials
- [ ] Configure SMTP settings
- [ ] Set error_reporting to 0
- [ ] Enable HTTPS
- [ ] Set secure session settings
- [ ] Implement rate limiting
- [ ] Regular security updates

### Performance Optimization
- [ ] Enable PHP OPcache
- [ ] Configure database indexing
- [ ] Implement caching for reports
- [ ] Optimize images and assets
- [ ] Use CDN for static files

## Support

For technical support or feature requests:
- Email: admin@om-engineers.org
- Organization: OM Engineers / Sammaan Foundation

## License

This system is proprietary software developed specifically for OM Engineers and Sammaan Foundation vehicle maintenance operations.

---

**Version**: 1.0.0
**Last Updated**: November 2024
**Developed by**: Claude Code for OM Engineers