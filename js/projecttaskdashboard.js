(function ($) {
  'use strict';

  const PROJECT_MAIN_FORCETAB = 'Project$main';
  const PROJECT_TASK_PREFIX = 'ProjectTask$';
  const DASHBOARD_FORCETAB_SUFFIX = 'DashboardTab$1';
  const DASHBOARD_FORCETAB = 'GlpiPlugin\\Projecttaskdashboard\\DashboardTab$1';

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

  function getForcetab(anchor) {
    try {
      return new URL(anchor.href, window.location.origin).searchParams.get('forcetab') || '';
    } catch (e) {
      return '';
    }
  }

  function normalizeProjectTabs(context) {
    const tabBars = new Set();

    $(context).find('a[href]').each(function () {
      const forcetab = getForcetab(this);
      if (
        forcetab !== PROJECT_MAIN_FORCETAB
        && !forcetab.startsWith(PROJECT_TASK_PREFIX)
        && !forcetab.endsWith(DASHBOARD_FORCETAB_SUFFIX)
      ) {
        return;
      }

      const bar = this.closest('ul.nav-tabs, .nav-tabs, [role="tablist"], ul');
      if (bar) {
        tabBars.add(bar);
      }
    });

    tabBars.forEach(function (bar) {
      let mainItem = null;
      let dashboardItem = null;
      const nativeTaskItems = [];

      $(bar).find('a[href]').each(function () {
        const forcetab = getForcetab(this);
        const item = this.closest('li, .nav-item');
        if (!item) {
          return;
        }

        if (forcetab === PROJECT_MAIN_FORCETAB) {
          mainItem = item;
        } else if (forcetab.startsWith(PROJECT_TASK_PREFIX)) {
          nativeTaskItems.push(item);
        } else if (forcetab.endsWith(DASHBOARD_FORCETAB_SUFFIX)) {
          dashboardItem = item;
        }
      });

      if (!mainItem || !dashboardItem) {
        return;
      }

      nativeTaskItems.forEach(function (item) {
        $(item).hide().attr('data-ptd-native-task-hidden', '1');
      });
      $(dashboardItem).insertAfter(mainItem);
    });
  }

  function normalizeMyTasksProjectLinks(context) {
    const roots = $(context)
      .closest('.projecttaskdashboard-mytasks')
      .add($(context).find('.projecttaskdashboard-mytasks'));

    roots.each(function () {
      $(this).find('a[href]').each(function () {
        let url;
        try {
          url = new URL(this.href, window.location.origin);
        } catch (e) {
          return;
        }

        if (!url.pathname.endsWith('/front/project.form.php') || !url.searchParams.get('id')) {
          return;
        }

        url.searchParams.set('forcetab', DASHBOARD_FORCETAB);
        this.href = url.toString();
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

  $(document).on('click', '.projecttaskdashboard-mytasks .savedsearches-item a', function (event) {
    const root = this.closest('.projecttaskdashboard-mytasks');
    if (!root) {
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
    const target = new URL(
      root.dataset.mytasksTarget || '/plugins/projecttaskdashboard/front/mytasks.php',
      window.location.origin
    );
    target.searchParams.set('savedsearches_id', savedSearchId);
    window.location.assign(target.toString());
  });

  $(document).on(
    'search_refresh.projecttaskdashboard',
    '.projecttaskdashboard-mytasks table.search-results',
    function () {
      normalizeMyTasksProjectLinks(this);
    }
  );

  $(document).on('glpi.tab.loaded.projecttaskdashboard', function () {
    bindDashboardSearchForms(document);
    normalizeProjectTabs(document);
    normalizeMyTasksProjectLinks(document);
  });

  $(function () {
    bindDashboardSearchForms(document);
    normalizeProjectTabs(document);
    normalizeMyTasksProjectLinks(document);
  });
})(jQuery);
