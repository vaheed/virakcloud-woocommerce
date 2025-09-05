# Virak Cloud WooCommerce Integration

[![WordPress](https://img.shields.io/badge/WordPress-6.2+-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0+-green.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.0+-red.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-orange.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Transform your WooCommerce store into a cloud service marketplace with the Virak Cloud WooCommerce Integration plugin. This powerful plugin enables you to resell virtual machines, manage cloud infrastructure, and provide customers with real-time control over their cloud instances.

## 🚀 Key Features

- **Cloud Service Marketplace**: Sell VMs, VPS, and cloud infrastructure through WooCommerce
- **VM Configurator**: Interactive product configuration with real-time pricing
- **Instance Management**: Full control over cloud instances (start, stop, reboot, delete)
- **Real-time Status**: Live instance status updates and monitoring
- **Snapshot Management**: Create, list, and manage VM snapshots
- **Console Access**: VNC console access for customer support
- **Multi-zone Support**: Deploy across multiple datacenter locations
- **Automated Provisioning**: Instant VM deployment upon purchase
- **Admin Dashboard**: Comprehensive admin interface for managing all instances
- **Setup Wizard**: Guided first-time configuration for easy setup
- **Persian Language Support**: Complete localization with RTL support

## 🎯 Perfect For

- **Web Hosting Companies** looking to expand into cloud services
- **IT Service Providers** wanting to offer infrastructure-as-a-service
- **Developers** needing to resell cloud resources
- **Agencies** managing client infrastructure
- **Enterprises** with internal cloud service needs

## 🛠️ Technical Features

- **RESTful API Integration**: Seamless connection to Virak Cloud infrastructure
- **WooCommerce Native**: Built specifically for WooCommerce compatibility
- **Responsive Design**: Works perfectly on all devices
- **Security First**: Secure credential storage and API communication
- **Performance Optimized**: Efficient instance management and status updates
- **Extensible Architecture**: Easy to customize and extend
- **Multi-language Support**: Built-in internationalization

## 📋 Requirements

- WordPress 6.2 or higher
- WooCommerce 7.0 or higher
- PHP 7.0 or higher
- Virak Cloud account and API credentials

## 🚀 Installation

1. **Upload Plugin Files**
   ```bash
   # Upload to your WordPress plugins directory
   wp-content/plugins/virakcloud-woo/
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Virak Cloud WooCommerce Integration"
   - Click "Activate"

3. **Configure Plugin**
   - Go to "Virak Cloud" → "Setup Wizard"
   - Follow the 5-step guided configuration
   - Enter your API credentials
   - Select your datacenter zones
   - Configure service offerings
   - Sync products to WooCommerce

4. **Start Selling**
   - Your cloud services are now available in WooCommerce
   - Customers can purchase and manage VMs
   - Monitor all instances from the admin dashboard

## 🎮 Quick Start Guide

### 1. Setup Wizard
The Setup Wizard guides you through the entire configuration process:

1. **API Connection** - Connect to Virak Cloud API
2. **Zone Selection** - Choose datacenter location
3. **Service Selection** - Configure VM plans, networks, and OS images
4. **Product Sync** - Create WooCommerce products
5. **Finish Setup** - Complete configuration with next steps

### 2. Admin Dashboard
Access the admin dashboard at **Virak Cloud** → **User Instances** to:
- View all customer instances
- Monitor instance status
- Manage snapshots
- Control instance operations
 - View credentials with convenient copy buttons for username, password and IP addresses (IP appears on the instance card)

### 3. Customer Experience
Customers can manage their instances from **My Account** → **Cloud Instances**:
- Start/Stop/Reboot VMs
- Access VNC console
  - View credentials with copy buttons (instance IP appears in the card header)
- Manage snapshots
- Monitor real-time status

## 🔧 Configuration

### API Settings
```php
// Configure in Setup Wizard or Settings page
API Base URL: https://public-api.virakcloud.com
API Token: Your authentication token
```

### Zone Configuration
- Select datacenter zones for your infrastructure
- Configure service offerings per zone
- Set up network configurations
- Choose available VM images

### Product Sync
- Automatically create WooCommerce products
- Customize product naming
- Set pricing and descriptions
- Configure product categories

## 📱 Features in Detail

### Instance Management
- **Real-time Status**: Live updates from Virak Cloud API
- **Smart Buttons**: Dynamic button display based on instance state
- **Bulk Operations**: Manage multiple instances efficiently
- **Status Refresh**: Manual and automatic status updates

### Snapshot Management
- **Create Snapshots**: Backup VM states
- **List Snapshots**: View all available snapshots
- **Revert Snapshots**: Restore from backup
- **Delete Snapshots**: Clean up old backups

### Security Features
- **Nonce Verification**: CSRF protection
- **User Permissions**: Role-based access control
- **Secure Storage**: Encrypted credential storage
- **API Security**: Secure communication with Virak Cloud

### User Interface
- **Responsive Design**: Works on all devices
- **Modern UI**: Clean, professional appearance
- **Intuitive Navigation**: Easy-to-use interface
- **Real-time Updates**: Live status and feedback

## 🌐 Persian Language Support

The plugin includes complete Persian (Farsi) language support:

- **Localized Interface**: All text in Persian
- **RTL Support**: Right-to-left text layout
- **Cultural Adaptations**: Persian-specific styling
- **Font Support**: Tahoma and Arial fonts

### Enabling Persian Language
1. Set WordPress language to Persian (فارسی)
2. Persian translations will automatically load
3. RTL layout will be applied automatically

## 🔌 API Integration

### Virak Cloud API Endpoints
- `/zones` - List available datacenter zones
- `/zone/{zoneId}/instance` - Instance management
- `/zone/{zoneId}/instance/{instanceId}/start` - Start instance
- `/zone/{zoneId}/instance/{instanceId}/stop` - Stop instance
- `/zone/{zoneId}/instance/{instanceId}/reboot` - Reboot instance
- `/zone/{zoneId}/instance/{instanceId}/snapshot` - Snapshot operations
- `/zone/{zoneId}/instance/{instanceId}/console` - VNC console access

### Authentication
- Bearer token authentication
- Secure credential storage
- Automatic token refresh
- Error handling and retry logic

## 🎨 Customization

### CSS Customization
```css
/* Custom instance card styling */
.vcw-card {
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

/* Custom button styling */
.vcw-btn--custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
```

### JavaScript Extensions
```javascript
// Hook into instance actions
document.addEventListener('vcw_instance_action', function(e) {
    console.log('Instance action:', e.detail);
});

// Custom status updates
function customStatusUpdate(card, data) {
    // Your custom logic here
}
```

### PHP Hooks
```php
// Filter instance data
add_filter('vcw_instance_data', function($data, $instance_id) {
    // Modify instance data
    return $data;
}, 10, 2);

// Action after instance creation
add_action('vcw_instance_created', function($instance_id, $zone_id) {
    // Your custom logic here
}, 10, 2);
```

## 🐛 Troubleshooting

### Common Issues

#### Connection Problems
- Verify API credentials in Setup Wizard
- Check network connectivity to Virak Cloud
- Ensure API token has proper permissions
- Check firewall settings

#### Instance Not Starting
- Verify zone configuration
- Check service offering availability
- Ensure network configuration is correct
- Review API error logs

#### Product Sync Issues
- Check WooCommerce is active
- Verify product permissions
- Review sync queue status
- Check for PHP memory limits

### Debug Mode
Enable debug logging in WordPress:
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Support Resources
- [Plugin Documentation](https://docs.virakcloud.com/woocommerce)
- [API Reference](https://api.virakcloud.com)
- [Support Portal](https://virakcloud.com/support)
- [Community Forum](https://community.virakcloud.com)

## 📈 Performance Optimization

### Caching
- Instance status caching
- API response caching
- Database query optimization
- Asset minification

### Database Optimization
- Efficient meta queries
- Indexed lookups
- Batch operations
- Cleanup routines

### API Optimization
- Request batching
- Connection pooling
- Response caching
- Error handling

## 🔒 Security Considerations

### Data Protection
- Encrypted credential storage
- Secure API communication
- User permission validation
- Input sanitization

### Access Control
- Role-based permissions
- Nonce verification
- Capability checks
- User ownership validation

### API Security
- Token-based authentication
- Request validation
- Rate limiting
- Error message sanitization

## 📊 Monitoring and Logging

### Activity Logs
- Instance operations
- User actions
- API calls
- Error tracking

### Performance Metrics
- Response times
- Success rates
- Error frequencies
- Resource usage

### Health Checks
- API connectivity
- Database performance
- Plugin status
- System resources

## 🚀 Future Roadmap

### Planned Features
- **Real-time Monitoring**: Advanced instance metrics
- **Bulk Operations**: Mass instance management
- **Advanced Filtering**: Enhanced search and filtering
- **Automated Backups**: Scheduled snapshot creation
- **Cost Optimization**: Usage-based pricing
- **Multi-tenant Support**: Advanced user management

### API Enhancements
- **Webhook Support**: Real-time notifications
- **GraphQL API**: Modern API interface
- **Rate Limiting**: Advanced throttling
- **Caching Layer**: Improved performance

### User Experience
- **Mobile App**: Native mobile application
- **Dashboard Widgets**: WordPress dashboard integration
- **Email Notifications**: Automated alerts
- **Advanced Reporting**: Detailed analytics

## 🤝 Contributing

We welcome contributions from the community! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Setup
```bash
# Clone the repository
git clone https://github.com/virakcloud/virakcloud-woo.git

# Install dependencies
composer install

# Run tests
phpunit

# Build assets
npm run build
```

### Code Standards
- Follow WordPress coding standards
- Use proper namespacing
- Include comprehensive documentation
- Write unit tests for new features

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 🙏 Credits

- **Developed by**: Virak Cloud Team
- **WordPress Integration**: Built with WordPress best practices
- **WooCommerce Compatibility**: Native WooCommerce integration
- **Community Support**: Open source community contributions

## 📞 Support

### Getting Help
- **Documentation**: [docs.virakcloud.com](https://docs.virakcloud.com)
- **Support Portal**: [virakcloud.com/support](https://virakcloud.com/support)
- **Community**: [community.virakcloud.com](https://community.virakcloud.com)
- **Email**: support@virakcloud.com

### Reporting Issues
- **GitHub Issues**: [github.com/virakcloud/virakcloud-woo/issues](https://github.com/virakcloud/virakcloud-woo/issues)
- **Bug Reports**: Include WordPress version, plugin version, and error logs
- **Feature Requests**: Describe use case and expected behavior

### Professional Support
- **Enterprise Support**: Dedicated support for enterprise customers
- **Custom Development**: Tailored solutions for specific needs
- **Training**: Comprehensive plugin training and workshops
- **Consulting**: Strategic guidance for cloud service implementation

---

**Made with ❤️ by the Virak Cloud Team**

For the latest updates and news, follow us on [Twitter](https://twitter.com/virakcloud) and [LinkedIn](https://linkedin.com/company/virakcloud).

