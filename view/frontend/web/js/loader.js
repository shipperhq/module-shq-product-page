/*
 * Shipper HQ
 *
 * @category ShipperHQ
 * @package ShipperHQ_Client
 * @copyright Copyright (c) 2019 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

let bundleUrl = sessionStorage.getItem('shq-ppse-bundle-override');
let shqPPSESettings = {};

try {
    shqPPSESettings = JSON.parse(sessionStorage.getItem('shq-ppse-settings'));
} catch (e) {
    console.error('[module-shq-product-page]: error parsing settings:', e)
}

if (!bundleUrl) {
    bundleUrl = shqPPSESettings.jsBundleUrl;
}

if (bundleUrl) {
    require([
        bundleUrl
    ], function () {
        if (window.shqProductPage) {
            window.shqProductPage.render(document.getElementById('shqPPSE'), shqPPSESettings)
        } else {
            console.error("[module-shq-product-page]: failed to load the js bundle")
        }
    });
} else {
    console.error("[module-shq-product-page]: bundleUrl is not set")
}
