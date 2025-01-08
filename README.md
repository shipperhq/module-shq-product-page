# ShipperHQ Product Page Shipping Calculator

## Overview

The Product Page Shipping Calculator is the latest addition to ShipperHQ’s arsenal of advanced features, designed to enhance a customer’s checkout experience. This feature enables customers to calculate and view estimated shipping costs and delivery dates directly on the product page, without needing to navigate to the checkout.

---

## Features

- **Real-Time Shipping Estimates**: Allow customers to view accurate shipping costs based on their location and chosen options.
- **Delivery Date Estimates**: Display estimated delivery dates directly on the product page.
- **Improved User Experience**: Streamline the shopping process by providing essential shipping information upfront.
- **Seamless Integration**: Works out-of-the-box with Magento 2 Luma theme.

---

## Requirements

- **Magento 2**: Compatible with Magento 2.4.4+
- **Luma Theme**: Compatible with the default Magento 2 Luma theme
- **Dependencies**: Requires either the `module-shq-server` or `module-shipper` extensions to be installed and configured
- **A valid ShipperHQ account**: [Sign up here](https://shipperhq.com/) for your 15-day free trial!

No additional configuration is required once the dependencies are in place.

---

## Installation

1. **Enable Maintenance Mode**:
   ```bash
   php bin/magento maintenance:enable
   ```

2. **Install via Composer**:
   ```bash
   composer require shipperhq/module-shq-productpage
   ```

3. **Enable Required Modules**:
   ```bash
   php bin/magento module:enable --clear-static-content ShipperHQ_ProductPage
   ```

4. **Flush Cache**:
   ```bash
   php bin/magento cache:flush
   ```

5. **Upgrade Setup**:
   ```bash
   php bin/magento setup:upgrade
   ```

6. **Compile Dependencies**:
   ```bash
   php bin/magento setup:di:compile
   ```

7. **Deploy Static Content**:
   ```bash
   php bin/magento setup:static-content:deploy
   ```

8. **Disable Maintenance Mode**:
   ```bash
   php bin/magento maintenance:disable
   ```

---

## Support

If you encounter any issues with this module, please open an issue on [GitHub](https://github.com/shipperhq/module-shq-product-page/issues).
Alternatively, contact ShipperHQ support at [support@shipperhq.com](mailto:support@shipperhq.com) or visit [https://shipperhq.com/contact](https://shipperhq.com/contact).

---

## Contribution

We welcome contributions to improve the module. The best way to contribute is to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

---

## License

See license files.

---

## Copyright

Copyright (c) 2021 Zowta LLC (http://www.ShipperHQ.com)

---
