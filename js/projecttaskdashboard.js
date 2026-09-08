(function ($) {
  'use strict';

  $(document).on('click', '.projecttaskdashboard .savedsearches-item a', function (event) {
    const root = this.closest('.projecttaskdashboard');
    if (!root || !root.dataset.dashboardTarget) {
      return;
    }

    let href;
    try {
      href = new URL(this.href, window.location.origin);
    } catch (e) {
      return;
    }

    const savedSearchId = href.searchParams.get('savedsearches_id');
    if (!savedSearchId) {
      return;
    }

    event.preventDefault();
    const target = new URL(root.dataset.dashboardTarget, window.location.origin);
    target.searchParams.set('savedsearches_id', savedSearchId);
    window.location.assign(target.toString());
  });
})(jQuery);
