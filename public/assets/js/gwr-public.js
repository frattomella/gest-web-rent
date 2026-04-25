(function () {
  document.addEventListener('click', function (event) {
    var thumb = event.target.closest('[data-gwr-gallery-thumb]');
    if (!thumb) {
      return;
    }

    var gallery = thumb.closest('.gwr-single-gallery');
    var main = gallery ? gallery.querySelector('[data-gwr-gallery-main]') : null;
    var next = thumb.getAttribute('data-gwr-gallery-thumb');

    if (main && next) {
      main.setAttribute('src', next);
      gallery.querySelectorAll('[data-gwr-gallery-thumb]').forEach(function (button) {
        button.classList.toggle('is-active', button === thumb);
      });
    }
  });
})();
