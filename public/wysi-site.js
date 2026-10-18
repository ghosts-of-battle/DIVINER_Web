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
 *          Its toolbar gets a bullet button that puts a plain "• " at the
 *          start of a line instead, which Arma shows as it is. Bullets live
 *          on the formatting toolbars only, never on a plain text box (user,
 *          2026-10-09); the html editors use Wysi's own list buttons.
 *          armatext.php turns the HTML into <t> tags on save and back on load.
 */

(function () {
  'use strict';
  if (typeof Wysi !== 'function') { return; }

  var align = { label: 'Text alignment', items: ['alignLeft', 'alignCenter', 'alignRight'] };
  var KINDS = {
    html: {
      tools: ['format', '|', 'bold', 'italic', 'underline', '|', align, '|', 'ul', 'ol', 'indent', 'outdent', '|', 'link', 'image']
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
  /* AFTER WYSI HAS BUILT ITS EDITORS. Wysi() defers to DOMContentLoaded while
     the page is still loading, and these bars reach into what it builds; a
     listener added after Wysi's runs after it. */
  function ready(fn) {
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fn); } else { fn(); }
  }

  ready(function () {
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

  });

  /* THE WELCOME EDITOR'S BULLET, on its toolbar beside the others. Arma has
     no lists, so this does not make one: every line the selection touches
     gets a plain "• " in front, or loses it if they all have one. The
     button has no data-action, so Wysi's own toolbar handler leaves it alone;
     the editor is told it changed so Wysi copies the HTML back. */
  var BLOCKS = 'p, h1, h2, h3, h4, div, li';
  var DOT = '• ';
  function firstText(el) {
    var w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    var t;
    while ((t = w.nextNode())) { if (t.nodeValue.replace(/ /g, ' ').trim() !== '') { return t; } }
    return null;
  }
  function bulletLines(box) {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) { return; }
    var range = sel.getRangeAt(0);
    if (!box.contains(range.commonAncestorContainer)) { return; }
    var blocks = Array.prototype.slice.call(box.querySelectorAll(BLOCKS))
      .filter(function (el) { return range.intersectsNode(el) && !el.querySelector(BLOCKS); });
    if (!blocks.length) {
      var n = range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentNode;
      var blk = n && n.closest && n.closest(BLOCKS);
      if (blk && box.contains(blk)) { blocks = [blk]; }
    }
    if (!blocks.length) { return; }
    var all = blocks.every(function (el) { var t = firstText(el); return t && /^\s*• ?/.test(t.nodeValue); });
    blocks.forEach(function (el) {
      var t = firstText(el);
      if (all) {
        t.nodeValue = t.nodeValue.replace(/^(\s*)• ?/, '$1');
      } else if (t) {
        t.nodeValue = t.nodeValue.replace(/^(\s*)/, '$1' + DOT);
      } else {
        el.insertBefore(document.createTextNode(DOT), el.firstChild);
      }
    });
    box.focus();
    box.dispatchEvent(new Event('input', { bubbles: true }));
  }
  ready(function () {
  Array.prototype.forEach.call(document.querySelectorAll('textarea[data-wysi="arma"]'), function (ta) {
    var wrap = ta.previousElementSibling;
    var bar = wrap && wrap.querySelector('.wysi-toolbar');
    var box = wrap && wrap.querySelector('.wysi-editor');
    if (!bar || !box) { return; }
    var sep = document.createElement('div');
    sep.className = 'wysi-separator';
    var b = document.createElement('button');
    b.type = 'button';
    b.title = 'Bullet';
    b.setAttribute('aria-label', 'Bullet');
    b.innerHTML = '<svg><use href="#wysi-ul"></use></svg>';
    b.addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep the selection
    b.addEventListener('click', function () { bulletLines(box); });
    bar.appendChild(sep);
    bar.appendChild(b);
  });
  });
})();
