/**
 * An HTML editor for the Web settings page, with no library.
 *
 * WHY NOT TINYMCE. The same reason editor.js gives for not loading
 * CodeMirror: this site has no build step and no third party on the wire,
 * and the page that writes the public home page should not be the one page
 * that fails when a CDN is down.
 *
 * HOW IT WORKS. The <textarea> stays the form field and holds the HTML. A
 * contenteditable box is drawn in its place with a toolbar, and every edit
 * copies the box's HTML back into the textarea. "HTML" swaps the box for the
 * textarea so the markup can be edited by hand, and swaps back. Pasting
 * inserts plain text; the toolbar adds the structure, so a page pasted from
 * a word processor does not arrive with its fonts and colours.
 *
 * The server cleans what is saved - see ghostd_html_clean() - so nothing
 * here is a security boundary. It is a convenience for writing a page.
 */

(function () {
  'use strict';

  /* label, command, argument. 'link', 'image' and 'source' are handled here;
     the rest go straight to execCommand. */
  var TOOLS = [
    ['Bold', 'bold'], ['Italic', 'italic'], ['Underline', 'underline'],
    ['Heading', 'formatBlock', '<h2>'], ['Subheading', 'formatBlock', '<h3>'],
    ['Paragraph', 'formatBlock', '<p>'], ['Quote', 'formatBlock', '<blockquote>'],
    ['Bullets', 'insertUnorderedList'], ['Numbered', 'insertOrderedList'],
    ['Link', 'link'], ['Unlink', 'unlink'], ['Picture', 'image'],
    ['Rule', 'insertHorizontalRule'], ['Clear', 'removeFormat'], ['HTML', 'source']
  ];

  function build(ta) {
    var wrap = document.createElement('div');
    var bar  = document.createElement('div');
    var box  = document.createElement('div');
    var source = false;

    wrap.className = 'hted';
    bar.className  = 'htedbar';
    box.className  = 'htedbox';
    box.contentEditable = 'true';
    box.innerHTML = ta.value;

    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(bar);
    wrap.appendChild(box);
    wrap.appendChild(ta);
    ta.hidden = true;
    ta.classList.add('htedsrc');

    function sync() { if (!source) { ta.value = box.innerHTML; } }

    function run(t) {
      if (t[1] === 'source') {
        if (source) {
          box.innerHTML = ta.value;      // hand edits come back into the box
        } else {
          ta.value = box.innerHTML;
        }
        source = !source;
        ta.hidden = !source;
        box.hidden = source;
        wrap.classList.toggle('src', source);
        (source ? ta : box).focus();
        return;
      }
      if (source) { return; }             // the toolbar is for the box
      box.focus();
      if (t[1] === 'link') {
        var url = window.prompt('Link address (https://...)');
        if (url) { document.execCommand('createLink', false, url); }
      } else if (t[1] === 'image') {
        var src = window.prompt('Picture address - a public Media file link, or https://...');
        if (src) { document.execCommand('insertImage', false, src); }
      } else {
        document.execCommand(t[1], false, t[2] || null);
      }
      sync();
    }

    TOOLS.forEach(function (t) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = t[0];
      b.title = t[0];
      /* mousedown would move the selection out of the box before the click. */
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () { run(t); });
      bar.appendChild(b);
    });

    box.addEventListener('input', sync);
    box.addEventListener('blur', sync);
    box.addEventListener('paste', function (e) {
      if (!e.clipboardData) { return; }
      var text = e.clipboardData.getData('text/plain');
      if (text) {
        e.preventDefault();
        document.execCommand('insertText', false, text);
      }
    });
    if (ta.form) { ta.form.addEventListener('submit', sync); }
  }

  Array.prototype.forEach.call(document.querySelectorAll('textarea[data-html-editor]'), build);
})();
