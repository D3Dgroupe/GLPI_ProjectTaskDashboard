(function ($) {
  'use strict';

  function canReloadTab() {
    return typeof window.reloadTab === 'function';
  }

  function reloadWithParams(params) {
    if (!canReloadTab()) {
      return false;
    }

    window.reloadTab(params.toString());
    return true;
  }

  function serializeSearchForm(form) {
    const params = new URLSearchParams();
    const ignored = new Set([
      'id',
      'forcetab',
      'itemtype',
      '_glpi_csrf_token',
      // Transport-only values used by GLPI's native Search Table AJAX.
      // A full dashboard tab reload already knows its current Project and
      // manages its own isolated search session.
      'ptd_project_id',
      'usesession'
    ]);

    $(form).serializeArray().forEach(function (field) {
      if (ignored.has(field.name) || field.name.startsWith('params[')) {
        return;
      }
      params.append(field.name, field.value);
    });

    return params;
  }

  function reloadSearchForm(form) {
    return reloadWithParams(serializeSearchForm(form));
  }

  function bindDashboardSearchForms(context) {
    $(context).find('.projecttaskdashboard form[data-glpi-search-form]').each(function () {
      const form = this;
      const $form = $(form);

      $form.off('.ptd');

      $form.on('click.ptd', 'button[name="search"]', function (event) {
        if (!canReloadTab()) {
          return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        reloadSearchForm(form);
      });

      $form.on('submit.ptd', function (event) {
        if (!canReloadTab()) {
          return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        reloadSearchForm(form);
      });

      $form.on('click.ptd', '.search-reset', function (event) {
        if (!canReloadTab()) {
          return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        window.reloadTab('reset=reset');
      });
    });
  }

  $(document).on('click', '.projecttaskdashboard .ptd-widget', function (event) {
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

    const params = new URLSearchParams();
    params.set('ptd_action', action);

    const state = href.searchParams.get('ptd_state');
    if (state !== null && state !== '') {
      params.set('ptd_state', state);
    }

    if (reloadWithParams(params)) {
      event.preventDefault();
    }
  });

  $(document).on('click', '.projecttaskdashboard .ptd-reset-filters', function (event) {
    if (!canReloadTab()) {
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

    const params = new URLSearchParams();
    params.set('savedsearches_id', savedSearchId);
    if (reloadWithParams(params)) {
      event.preventDefault();
      return;
    }

    event.preventDefault();
    const target = new URL(root.dataset.dashboardTarget, window.location.origin);
    target.searchParams.set('savedsearches_id', savedSearchId);
    window.location.assign(target.toString());
  });

  $(document).on('glpi.tab.loaded.projecttaskdashboard', function () {
    bindDashboardSearchForms(document);
  });

  $(function () {
    bindDashboardSearchForms(document);
  });
})(jQuery);
