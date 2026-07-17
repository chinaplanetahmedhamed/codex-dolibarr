(function () {
  'use strict';
  var root = document.documentElement;
  var allowedThemes = ['light', 'dark', 'system'];
  var api = {
    setTheme: function (theme) {
      if (allowedThemes.indexOf(theme) === -1) return false;
      root.setAttribute('data-cpui-theme', theme);
      try { localStorage.setItem('cpui-theme', theme); } catch (e) {}
      return true;
    },
    setHighContrast: function (enabled) {
      root.setAttribute('data-cpui-contrast', enabled ? 'high' : 'normal');
      try { localStorage.setItem('cpui-contrast', enabled ? 'high' : 'normal'); } catch (e) {}
    }
  };
  var theme = 'system';
  var contrast = 'normal';
  try { theme = localStorage.getItem('cpui-theme') || theme; contrast = localStorage.getItem('cpui-contrast') || contrast; } catch (e) {}
  api.setTheme(theme);
  root.setAttribute('data-cpui-contrast', contrast === 'high' ? 'high' : 'normal');
  window.VisionTradingUI = api;
}());

