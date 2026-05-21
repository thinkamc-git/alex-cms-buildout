/* analytics.js — site-wide Google Analytics 4 loader.
 *
 * Loaded as <script src="/_layout/analytics.js" async> from the <head>
 * of every public page (marketing pages, ux2.0 article, CMS-rendered
 * articles via master-layout.php). NOT loaded from any /cms/* admin
 * page — admin traffic is single-user noise.
 *
 * Single source of truth for the GA Measurement ID: change the
 * GA_ID constant below and redeploy to update every page at once.
 */
(function () {
  var GA_ID = 'G-J6443HD1JY';

  if (!GA_ID || GA_ID.indexOf('PLACEHOLDER') !== -1) return;

  var s = document.createElement('script');
  s.async = true;
  s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
  document.head.appendChild(s);

  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', GA_ID);
})();
