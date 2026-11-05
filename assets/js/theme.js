(function () {
  var t = 'dark';
  try { t = localStorage.getItem('tms-theme') || 'dark'; } catch (e) {}
  document.documentElement.setAttribute('data-theme', t);
})();
