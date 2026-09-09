(function ($) {
  'use strict';

  $(document).on('click', '.projecttaskdashboard .ptd-widget', function (event) {
    if (typeof window.reloadTab !== 'function') {
      return;
    }

    let href;
    try {
      href = new URL(this.href, window.location.origin);
    } catch (e) {
      return;
    }

    const action = href.searchParams.get('ptd_action');
    if (!action) {
      return;
    }

    event.preventDefault();

    const params = new URLSearchParams();
    params.set('ptd_action', action);

    const state = href.searchParams.get('ptd_state');
    if (state !== null && state !== '') {
      params.set('ptd_state', state);
    }

    window.reloadTab(params.toString());
  });

  $(document).on('click', '.projecttaskdashboard .ptd-reset-filters', function (event) {
    if (typeof window.reloadTab !== 'function') {
      return;
    }

    event.preventDefault();
    window.reloadTab('reset=reset');
  });

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

    if (typeof window.reloadTab === 'function') {
      const params = new URLSearchParams();
      params.set('savedsearches_id', savedSearchId);
      window.reloadTab(params.toString());
      return;
    }

    const target = new URL(root.dataset.dashboardTarget, window.location.origin);
    target.searchParams.set('savedsearches_id', savedSearchId);
    window.location.assign(target.toString());
  });
})(jQuery);
