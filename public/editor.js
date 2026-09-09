/**
 * Syntax highlighting for the JSON editors, with no library.
 *
 * WHY NOT CODEMIRROR. The rest of this site has no build step and no
 * dependencies - db.php talks to Mongo on the raw driver, steam.php writes
 * OpenID out by hand. Pulling 200KB of editor off a CDN so somebody can see
 * their keys in a different colour would be the only thing here that fails
 * when a third party is down, on the page that edits the live database.
 *
 * HOW IT WORKS. A <pre> holds the highlighted copy; the real <textarea> sits
 * exactly on top with transparent text and a visible caret. Every metric that
 * affects where a glyph lands - font, size, line height, padding, tab size,
 * wrapping - has to match between the two, or the highlight drifts from the
 * text. That is what .editor in style.css guarantees.
 */

(function () {
  'use strict';

  function esc(s) {
    return s.replace(/[&<>]/g, function (c) {
      return c === '&' ? '&amp;' : c === '<' ? '&lt;' : '&gt;';
    });
  }

  /* Strings are matched before anything else, so a brace inside a string is
     not mistaken for punctuation. A key is a string followed by a colon. */
  var TOKEN = /("(?:\\.|[^"\\])*")(\s*:)|("(?:\\.|[^"\\])*")|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)|\b(true|false|null)\b|([{}\[\],:])/g;

  function highlight(src) {
    var out = '';
    var last = 0;
    var m;
    TOKEN.lastIndex = 0;
    while ((m = TOKEN.exec(src)) !== null) {
      out += esc(src.slice(last, m.index));
      if (m[1]) {
        out += '<span class="j-key">' + esc(m[1]) + '</span>' + esc(m[2]);
      } else if (m[3]) {
        out += '<span class="j-str">' + esc(m[3]) + '</span>';
      } else if (m[4]) {
        out += '<span class="j-num">' + esc(m[4]) + '</span>';
      } else if (m[5]) {
        out += '<span class="j-lit">' + esc(m[5]) + '</span>';
      } else {
        out += '<span class="j-pun">' + esc(m[6]) + '</span>';
      }
      last = TOKEN.lastIndex;
    }
    out += esc(src.slice(last));
    /* A trailing newline is not painted by <pre> unless something follows it,
       so the last line would sit half a row off. */
    return out + '\n';
  }

  /** Where the error is, in "line N" terms rather than a character offset. */
  function whereIs(text, message) {
    var m = /position (\d+)/.exec(message || '');
    if (!m) { return message; }
    var upto = text.slice(0, parseInt(m[1], 10));
    var line = upto.split('\n').length;
    var col = upto.length - upto.lastIndexOf('\n');
    return message.replace(/at position \d+/, 'on line ' + line + ', column ' + col);
  }

  function attach(ta) {
    var wrap = document.createElement('div');
    wrap.className = 'editor';
    var pre = document.createElement('pre');
    pre.className = 'editor-hl';
    pre.setAttribute('aria-hidden', 'true');
    var code = document.createElement('code');
    pre.appendChild(code);

    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(pre);
    wrap.appendChild(ta);

    var status = document.createElement('p');
    status.className = 'editor-status';
    wrap.parentNode.insertBefore(status, wrap.nextSibling);

    function paint() {
      code.innerHTML = highlight(ta.value);
      pre.scrollTop = ta.scrollTop;
      pre.scrollLeft = ta.scrollLeft;

      if (ta.value.trim() === '') {
        status.textContent = '';
        status.className = 'editor-status';
        return;
      }
      try {
        JSON.parse(ta.value);
        status.textContent = 'valid JSON';
        status.className = 'editor-status ok';
      } catch (e) {
        status.textContent = whereIs(ta.value, e.message);
        status.className = 'editor-status bad';
      }
    }

    ta.addEventListener('input', paint);
    ta.addEventListener('scroll', function () {
      pre.scrollTop = ta.scrollTop;
      pre.scrollLeft = ta.scrollLeft;
    });

    /* Tab indents instead of leaving the field: this is an editor, and the
       next control is a Save button that replaces a document. */
    ta.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab' || e.ctrlKey || e.altKey || e.metaKey) { return; }
      e.preventDefault();
      var s = ta.selectionStart;
      var end = ta.selectionEnd;
      ta.value = ta.value.slice(0, s) + '  ' + ta.value.slice(end);
      ta.selectionStart = ta.selectionEnd = s + 2;
      paint();
    });

    paint();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var list = document.querySelectorAll('textarea[data-editor="json"]');
    for (var i = 0; i < list.length; i++) { attach(list[i]); }
  });
})();
