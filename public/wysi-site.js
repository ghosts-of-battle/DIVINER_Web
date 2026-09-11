/**
 * Wysi on this site: which tools each editor gets, the theme it follows, and
 * the colour button the welcome editor needs.
 *
 * WYSI, VENDORED. public/wysi.min.js and wysi.min.css are the dist files of
 * https://github.com/mdbassit/Wysi (MIT, Momo Bassit) copied in as they are,
 * so nothing loads from a CDN - the rule the rest of the site keeps. Update
 * them by copying the two files again; this file is the only glue.
 *
 * TWO KINDS OF EDITOR, by the textarea's data-wysi value:
 *   html - the front page's About block: ordinary web HTML, cleaned on save
 *          by ghostd_html_clean().
 *   arma - the welcome screen: only what Arma structured text can show.
 *          Headings are sizes, bold is a font, underline is underline,
 *          alignment is alignment, colour is colour; no italic, no lists.
 *          armatext.php turns the HTML into <t> tags on save and back on load.
 */

(function () {
  'use strict';
  if (typeof Wysi !== 'function') { return; }

  var align = { label: 'Text alignment', items: ['alignLeft', 'alignCenter', 'alignRight'] };
  var KINDS = {
    html: {
      tools: ['format', '|', 'bold', 'italic', 'underline', '|', align, '|', 'ul', 'ol', '|', 'link', 'image']
    },
    arma: {
      tools: ['format', '|', 'bold', 'underline', '|', align, '|', 'link', 'image'],
      /* The colour button writes <span style="color:..."> and a loaded size
         is <span style="font-size:..em">; Wysi drops any tag it is not told
         about, so it is told about these. */
      customTags: [{ tags: ['span'], styles: ['color', 'font-size'] }]
    }
  };

  /* Dark or light is read off the page's own ground colour, so every theme
     and every unit scheme gets the right toolbar without a list here. */
  function dark() {
    var g = getComputedStyle(document.documentElement).getPropertyValue('--ground').trim();
    var m = /^#([0-9a-f]{6})$/i.exec(g);
    if (!m) { return true; }
    var n = parseInt(m[1], 16);
    var l = (0.2126 * (n >> 16) + 0.7152 * ((n >> 8) & 255) + 0.0722 * (n & 255)) / 255;
    return l < 0.5;
  }

  Object.keys(KINDS).forEach(function (kind) {
    var sel = 'textarea[data-wysi="' + kind + '"]';
    if (!document.querySelector(sel)) { return; }
    var opts = { el: sel, darkMode: dark(), autoGrow: true, height: 260 };
    Object.keys(KINDS[kind]).forEach(function (k) { opts[k] = KINDS[kind][k]; });
    Wysi(opts);
  });

  /* The theme picker changes the page live; the editors follow it. */
  var pick = document.getElementById('themepick');
  if (pick) {
    pick.addEventListener('change', function () {
      setTimeout(function () {
        var d = dark();
        Array.prototype.forEach.call(document.querySelectorAll('.wysi-wrapper'), function (w) {
          w.classList.toggle('wysi-darkmode', d);
        });
      }, 0);
    });
  }

  /* Colour, for the welcome editor: a picker and two buttons in a bar that
     names its textarea. Wysi has no colour tool; the browser's own command
     does it, written as CSS so the span is one Wysi keeps. "Plain" strips the
     selection's inline formatting - colour, bold and underline together. */
  Array.prototype.forEach.call(document.querySelectorAll('[data-wysi-colour]'), function (bar) {
    var input = bar.querySelector('input[type=color]');
    var ta = document.querySelector(bar.getAttribute('data-wysi-colour'));
    var wrap = ta && ta.previousElementSibling;       // Wysi puts its wrapper before the textarea
    var box = wrap && wrap.querySelector('.wysi-editor');
    if (!input || !box) { return; }

    function apply(cmd, value) {
      box.focus();
      document.execCommand('styleWithCSS', false, true);
      document.execCommand(cmd, false, value);
      document.execCommand('styleWithCSS', false, false);
    }
    Array.prototype.forEach.call(bar.querySelectorAll('button'), function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep the selection
    });
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-do]');
      if (!b) { return; }
      if (b.getAttribute('data-do') === 'colour') {
        apply('foreColor', input.value);
      } else {
        apply('removeFormat', null);
      }
    });
  });
})();
