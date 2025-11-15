=== ShopAnalytics Lite – WooCommerce Sales & Customer Reports ===
Contributors: maidulcu
Tags: woocommerce, analytics, customer-insights, sales, reports
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Instant, lightweight WooCommerce reporting. Track revenue, orders, top customers, and export to CSV. Fast insights for shop owners.

== Description ==

**ShopAnalytics Lite** is a performance-focused analytics plugin for WooCommerce store owners. Instantly see your shop's revenue trends, top customers, repeat purchase behavior, and more — all without slowing down your site.

Whether you're just getting started or managing a growing WooCommerce store, ShopAnalytics Lite gives you the insights you need to grow sales with confidence.

**🔍 Why Choose ShopAnalytics Lite?**
- Lightweight, no performance overhead
- Simple UI for store owners — no analytics experience needed
- Quick setup — view reports in under a minute

== Features ==

📊 **Sales Trends**
- Visual dashboard of monthly revenue and orders
- Dual-axis chart for comparing revenue vs. order trends
- Supports date range filtering

📈 **Revenue & AOV Tracking**
- Average Order Value (AOV) tracked per month
- Great for understanding customer spending behavior

🧑‍🤝‍🧑 **Customer Insights**
- View top customers by spend and order volume
- Drill into customer lifetime value (CLV)
- Analyze repeat vs new customers

📤 **Export to CSV**
- Export top customer data
- Export revenue metrics
- Export order data

📦 **Product Category Analytics**
- Revenue and quantity sold per category
- Toggle between revenue view and quantity view
- Helps you identify best-performing product categories

🌍 **Geographic Insights**
- View customer distribution by country
- Simple doughnut chart for fast visual breakdown

🧠 **Customer Segmentation**
- Buckets by order count (e.g., 1, 2–3, 4–5, 6+)
- Bar chart visualization to identify retention and loyalty groups

🧩 **Built for WooCommerce**
- Uses WooCommerce’s native orders and customer tables
- No custom tables or heavy overhead

⚙️ **Clean & Secure Code**
- Nonces and capability checks throughout
- Follows WordPress coding and escaping best practices

⚡ **Performance Focused**
- Optional lightweight mode disables all charts for resource-limited hosts
- Smart queries for large stores

== Installation ==

1. Upload the `shopanalytics-lite` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin via **Plugins → Installed Plugins** in WordPress.
3. Go to **ShopAnalytics → Setup** to connect with WooCommerce.
4. Done! Start viewing reports from your admin dashboard.
5. (Optional) For translations, add `.po`/`.mo` files in `wp-content/plugins/shopanalytics-lite/languages/`.

== Frequently Asked Questions ==

= Is ShopAnalytics Lite compatible with my WooCommerce store? =  
Yes, absolutely. ShopAnalytics Lite works seamlessly with all WooCommerce stores using standard WooCommerce data tables.

= Will ShopAnalytics delete any data when uninstalled? =  
No. Your store data is never deleted. You can optionally enable a cleanup option to remove plugin-related settings and logs.

= Can I export reports and data? =  
Yes. You can export customer lists, sales trends, and other data as CSV directly from the dashboard.

== Screenshots ==

1. `assets/screenshot-1.png` — Overview dashboard with key sales metrics
2. `assets/screenshot-2.png` — Customer insights and export options
3. `assets/screenshot-3.png` — Setup wizard and Pro upgrade notice

== Changelog ==

= 1.0.1 =
* **Security:** Fixed SQL injection vulnerabilities in database queries - now using proper prepared statements throughout
* **Security:** Improved SQL query construction in admin UI for better security practices
* **Performance:** Optimized N+1 query problems in customer lookups - reduced database calls by 100x for stores with many customers
* **Performance:** Optimized repeat customer detection to use batch queries instead of individual lookups
* **Bug Fix:** Removed duplicate 'Reports' submenu registration
* **Bug Fix:** Resolved Chart.js version inconsistency (now consistently using v4.5.0)
* **Bug Fix:** Fixed cache key generation to use proper serialization instead of wildcard patterns
* **Code Quality:** Extracted hardcoded order status values to class constants for better maintainability
* **License:** Standardized license to GPLv2 for WordPress.org compliance

= 1.0.0 =
* Initial release: Sales dashboard, customer insights, CSV export, Freemius integration.

== Upgrade Notice ==

= 1.0.1 =
Critical security and performance update. Fixes SQL injection vulnerabilities and significantly improves performance for stores with many customers. Update recommended.

= 1.0.0 =
Initial release — lightweight WooCommerce sales analytics.

== Support ==

Need help or have feature requests?  
👉 Visit: [https://dynamicweblab.com](https://dynamicweblab.com)