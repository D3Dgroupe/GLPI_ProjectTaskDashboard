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
      const forcetabs = new URL(anchor.href, window.location.origin).searchParams.getAll('forcetab');
      return forcetabs[forcetabs.length - 1] || '';
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

  // Issue #20: paint the État cell with its ProjectState color, and the whole
  // row when that state is flagged "État terminé". The palette comes from the
  // container's data-ptd-state-palette; GLPI's own cell only shows a small
  // color square (.badge_block) with the state name.
  const STATE_SEARCH_OPTION = '12';

  function matchState(palette, label) {
    const name = String(label).trim();
    return palette.find(function (state) {
      return String(state.name).trim() === name;
    }) || null;
  }

  function paintStateRows(context) {
    $(context).find('[data-ptd-state-palette]').addBack('[data-ptd-state-palette]').each(function () {
      const palette = $(this).data('ptdStatePalette');
      if (!Array.isArray(palette) || palette.length === 0) {
        return;
      }

      $(this).find('table.search-results').each(function () {
        const table = $(this);
        const header = table.find('thead th[data-searchopt-id="' + STATE_SEARCH_OPTION + '"]').first();
        if (header.length === 0) {
          return;
        }
        const column = header.index();

        table.find('tbody > tr').each(function () {
          const row = $(this);
          const cell = row.children('td').eq(column);
          const state = cell.length ? matchState(palette, cell.text()) : null;
          row.removeClass('ptd-row-finished');
          cell.removeClass('ptd-state-cell');
          if (!state) {
            return;
          }

          cell.addClass('ptd-state-cell');
          cell[0].style.setProperty('--ptd-state-bg', state.bg);
          cell[0].style.setProperty('--ptd-state-fg', state.fg);
          if (state.finished) {
            row.addClass('ptd-row-finished');
            this.style.setProperty('--ptd-state-bg', state.bg);
            this.style.setProperty('--ptd-state-fg', state.fg);
          }
        });
      });
    });
  }

  window.ProjectTaskDashboardStates = { match: matchState };

  $(document).on('search_refresh.projecttaskdashboard-states', function (event) {
    paintStateRows($(event.target).closest('[data-ptd-state-palette]'));
  });

  // Issue #21: edit État / Type / % effectué / Date de fin planifiée straight
  // from the table. Click a cell -> the server describes the task (current
  // raw values, options, what the user may edit) -> an inline control saves
  // on change through ProjectTask::update(), then the table is refreshed
  // with GLPI's own Search Table (keeps filters, sort and page).
  const INLINE_FIELDS = {
    '12': 'projectstates_id',
    '14': 'projecttasktypes_id',
    '5': 'percent_done',
    '8': 'plan_end_date'
  };
  const INLINE_CELL_SELECTOR = Object.keys(INLINE_FIELDS).map(function (id) {
    return '[data-ptd-inline-edit-url] table.search-results tbody td[data-searchopt-content-id="' + id + '"]';
  }).join(', ');

  function taskIdFromRow(row) {
    const checkbox = row.querySelector('input.massive_action_checkbox[name^="item[ProjectTask]["]');
    if (checkbox) {
      const match = /^item\[ProjectTask\]\[(\d+)\]$/.exec(checkbox.getAttribute('name') || '');
      if (match) {
        return parseInt(match[1], 10);
      }
    }
    const links = row.querySelectorAll('a[href*="projecttask.form.php"]');
    for (let i = 0; i < links.length; i++) {
      const match = /[?&]id=(\d+)/.exec(links[i].getAttribute('href') || '');
      if (match) {
        return parseInt(match[1], 10);
      }
    }
    return null;
  }

  // "2026-09-30 18:00:00" <-> "2026-09-30T18:00" (datetime-local).
  function toDatetimeLocal(value) {
    const match = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/.exec(String(value || ''));
    return match ? match[1] + 'T' + match[2] : '';
  }

  function csrfToken() {
    if (typeof window.getAjaxCsrfToken === 'function') {
      return window.getAjaxCsrfToken();
    }
    const meta = document.querySelector('meta[property="glpi:csrf_token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  async function inlineRequest(url, options) {
    const response = await fetch(url, Object.assign({
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-Glpi-Csrf-Token': csrfToken()
      }
    }, options || {}));
    let payload = null;
    try {
      payload = await response.json();
    } catch (e) {
      payload = null;
    }
    if (!response.ok || !payload || !payload.success) {
      throw new Error((payload && payload.message) || 'La modification a échoué.');
    }
    return payload;
  }

  function buildInlineControl(field, description) {
    const current = description.values[field];
    let control;
    if (field === 'plan_end_date') {
      control = document.createElement('input');
      control.type = 'datetime-local';
      control.value = toDatetimeLocal(current);
    } else {
      control = document.createElement('select');
      const options = description.options[field] || [];
      options.forEach(function (option) {
        const el = document.createElement('option');
        if (typeof option === 'number') {
          el.value = String(option);
          el.textContent = option + ' %';
        } else {
          el.value = String(option.id);
          el.textContent = option.name;
        }
        control.appendChild(el);
      });
      control.value = String(current === null || current === undefined ? 0 : current);
      if (field === 'percent_done' && control.value !== String(current)) {
        // Value off the 5% grid (set elsewhere): keep it selectable as is.
        const el = document.createElement('option');
        el.value = String(current);
        el.textContent = current + ' %';
        control.insertBefore(el, control.firstChild);
        control.value = String(current);
      }
    }
    control.className = 'form-control form-control-sm ptd-inline-editor';
    return control;
  }

  function refreshDashboardTable(root, table) {
    const container = $(table).closest('.ajax-container.search-display-data');
    const jsClass = container.data('js_class');
    if (jsClass && jsClass.view && typeof jsClass.view.refreshResults === 'function') {
      jsClass.view.refreshResults();
    } else if (typeof window.reloadTab === 'function' && root.classList.contains('projecttaskdashboard')) {
      window.reloadTab('');
      return;
    } else {
      window.location.reload();
      return;
    }

    const projectId = root.getAttribute('data-ptd-project-id');
    if (!projectId) {
      return;
    }
    const url = new URL(root.getAttribute('data-ptd-inline-edit-url'), window.location.origin);
    url.searchParams.set('action', 'counts');
    url.searchParams.set('project_id', projectId);
    inlineRequest(url.toString()).then(function (payload) {
      Object.keys(payload.counts || {}).forEach(function (key) {
        $(root).find('[data-ptd-widget-key="' + key + '"] .ptd-widget-count').text(payload.counts[key]);
      });
    }).catch(function () {
      // Counters are a convenience: they catch up on the next tab load.
    });
  }

  async function openInlineEditor(cell) {
    const root = cell.closest('[data-ptd-inline-edit-url]');
    const row = cell.closest('tr');
    const table = cell.closest('table');
    const field = INLINE_FIELDS[cell.getAttribute('data-searchopt-content-id')];
    const taskId = row ? taskIdFromRow(row) : null;
    if (!root || !field || !taskId || cell.classList.contains('ptd-inline-editing')) {
      return;
    }

    const originalHtml = cell.innerHTML;
    cell.classList.add('ptd-inline-editing');
    const restore = function () {
      cell.innerHTML = originalHtml;
      cell.classList.remove('ptd-inline-editing');
    };

    const baseUrl = root.getAttribute('data-ptd-inline-edit-url');
    let description;
    try {
      const url = new URL(baseUrl, window.location.origin);
      url.searchParams.set('id', String(taskId));
      description = await inlineRequest(url.toString());
    } catch (error) {
      restore();
      window.alert(error.message);
      return;
    }

    if (!Array.isArray(description.editable) || description.editable.indexOf(field) === -1) {
      restore();
      window.alert(field === 'percent_done' && description.auto_percent_done
        ? 'Le pourcentage de cette tâche est calculé automatiquement à partir de ses sous-tâches.'
        : 'Vous n\'avez pas le droit de modifier cette tâche.');
      return;
    }

    const control = buildInlineControl(field, description);
    const initialValue = control.value;
    let saving = false;
    cell.innerHTML = '';
    cell.appendChild(control);
    control.focus();

    const save = async function () {
      if (saving) {
        return;
      }
      if (control.value === initialValue) {
        restore();
        return;
      }
      saving = true;
      control.disabled = true;
      const body = new FormData();
      body.set('id', String(taskId));
      body.set('field', field);
      body.set('value', control.value);
      try {
        await inlineRequest(baseUrl, { method: 'POST', body: body });
        refreshDashboardTable(root, table);
      } catch (error) {
        restore();
        window.alert(error.message);
      }
    };

    control.addEventListener('change', save);
    control.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        saving = true;
        restore();
      } else if (event.key === 'Enter') {
        event.preventDefault();
        save();
      }
    });
    control.addEventListener('blur', function () {
      // Let a pending "change" (select / date picker) win over the blur.
      window.setTimeout(function () {
        if (!saving && cell.contains(control)) {
          save();
        }
      }, 150);
    });
  }

  window.ProjectTaskDashboardInlineEdit = {
    taskIdFromRow: taskIdFromRow,
    toDatetimeLocal: toDatetimeLocal,
    fields: INLINE_FIELDS
  };

  $(document).on('click.projecttaskdashboard-inline', INLINE_CELL_SELECTOR, function (event) {
    if ($(event.target).closest('a, .ptd-inline-editor').length) {
      return;
    }
    openInlineEditor(this);
  });

  // Issue #22: open tasks from the table in a modal instead of navigating
  // away, so the list (filters, sort, page, scroll) is still there after.
  // GLPI's projecttask.form.php renders just the task form with _in_modal=1
  // (no menus); saving inside the iframe stays inside it (Html::back()). The
  // table is refreshed on close only if something was submitted.
  // Ctrl/Cmd/Shift/middle click keep the native "open in a new tab" behaviour.
  const TASK_MODAL_ID = 'ptd-task-modal';
  const TASK_LINK_SELECTOR = '[data-ptd-inline-edit-url] table.search-results tbody a[href*="projecttask.form.php"]';

  function taskModalUrl(href) {
    let url;
    try {
      url = new URL(href, window.location.origin);
    } catch (e) {
      return null;
    }
    if (!/projecttask\.form\.php$/.test(url.pathname) || !/^\d+$/.test(url.searchParams.get('id') || '')) {
      return null;
    }
    url.searchParams.delete('forcetab');
    url.searchParams.set('_in_modal', '1');
    return url.toString();
  }

  function taskModalElement() {
    let modal = document.getElementById(TASK_MODAL_ID);
    if (modal) {
      return modal;
    }
    modal = document.createElement('div');
    modal.id = TASK_MODAL_ID;
    modal.className = 'modal fade';
    modal.tabIndex = -1;
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML =
      '<div class="modal-dialog modal-xl modal-dialog-centered">' +
        '<div class="modal-content">' +
          '<div class="modal-header">' +
            '<h5 class="modal-title text-truncate" data-ptd-task-modal-title></h5>' +
            '<a class="btn btn-sm btn-ghost-secondary ms-auto me-2" target="_blank" rel="noopener" data-ptd-task-modal-full>' +
              '<i class="ti ti-external-link me-1"></i>Ouvrir la fiche complète' +
            '</a>' +
            '<button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="Fermer"></button>' +
          '</div>' +
          '<div class="modal-body p-0">' +
            '<iframe class="ptd-task-modal-frame" title="Tâche de projet"></iframe>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);
    return modal;
  }

  function openTaskModal(link) {
    const modalUrl = taskModalUrl(link.href);
    const root = link.closest('[data-ptd-inline-edit-url]');
    const table = link.closest('table');
    if (!modalUrl || !root || typeof window.bootstrap === 'undefined') {
      return false;
    }

    const modal = taskModalElement();
    const frame = modal.querySelector('iframe');
    modal.querySelector('[data-ptd-task-modal-title]').textContent = link.textContent.trim() || 'Tâche de projet';
    modal.querySelector('[data-ptd-task-modal-full]').href = link.href;

    // First load = the form itself; any further load = a form was submitted.
    let loads = 0;
    frame.onload = function () {
      loads++;
    };
    frame.src = modalUrl;

    $(modal).off('hidden.bs.modal.ptd').one('hidden.bs.modal.ptd', function () {
      frame.onload = null;
      frame.src = 'about:blank';
      if (loads > 1) {
        refreshDashboardTable(root, table);
      }
    });

    window.bootstrap.Modal.getOrCreateInstance(modal).show();
    return true;
  }

  window.ProjectTaskDashboardTaskModal = { url: taskModalUrl };

  $(document).on('click.projecttaskdashboard-modal', TASK_LINK_SELECTOR, function (event) {
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
      return;
    }
    if (openTaskModal(this)) {
      event.preventDefault();
      event.stopPropagation();
    }
  });

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
    paintStateRows(document);
    bindDashboardSearchForms(document);
    normalizeProjectTabs(document);
    normalizeMyTasksProjectLinks(document);
  });

  $(function () {
    paintStateRows(document);
    bindDashboardSearchForms(document);
    normalizeProjectTabs(document);
    normalizeMyTasksProjectLinks(document);
  });
})(jQuery);
