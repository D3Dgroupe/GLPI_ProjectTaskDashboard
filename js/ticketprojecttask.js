(function ($) {
  'use strict';

  const NATIVE_LINK_FORM = 'form[action*="projecttask_ticket.form.php"]';
  const CREATE_ACTION_ATTR = 'data-ptd-create-project-task';
  const MODAL_ID = 'ptd-ticket-project-task-modal';

  function endpointFromNativeForm(form) {
    const url = new URL(form.action, window.location.origin);
    const suffix = '/front/projecttask_ticket.form.php';
    const index = url.pathname.lastIndexOf(suffix);
    const root = index >= 0 ? url.pathname.substring(0, index) : '';
    return root + '/plugins/projecttaskdashboard/front/ticket-project-task-create.php';
  }

  function selectedProjectId(form) {
    return parseInt($(form).find('select[name="projects_id"]').first().val(), 10) || 0;
  }

  function ticketId(form) {
    return parseInt($(form).find('input[name="tickets_id"]').first().val(), 10) || 0;
  }

  function syncButton(form, button) {
    button.disabled = selectedProjectId(form) <= 0 || ticketId(form) <= 0;
  }

  function showLoadError(message) {
    window.alert(message || 'Impossible de charger le formulaire de création de tâche.');
  }

  async function openCreateModal(form) {
    const projectId = selectedProjectId(form);
    const currentTicketId = ticketId(form);
    if (projectId <= 0 || currentTicketId <= 0) {
      return;
    }

    const endpoint = new URL(endpointFromNativeForm(form), window.location.origin);
    endpoint.searchParams.set('ticket_id', String(currentTicketId));
    endpoint.searchParams.set('project_id', String(projectId));

    const response = await fetch(endpoint.toString(), {
      method: 'GET',
      credentials: 'same-origin',
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    const html = await response.text();
    if (!response.ok) {
      showLoadError($('<div>').html(html).text());
      return;
    }

    $('#' + MODAL_ID).remove();
    $('body').append(html);

    const modalElement = document.getElementById(MODAL_ID);
    if (!modalElement) {
      showLoadError();
      return;
    }

    modalElement.addEventListener('hidden.bs.modal', function () {
      modalElement.remove();
    }, {once: true});

    const modal = new bootstrap.Modal(modalElement, {});
    modal.show();
  }

  function injectTicketProjectTaskCreateAction(context) {
    $(context).find(NATIVE_LINK_FORM).addBack(NATIVE_LINK_FORM).each(function () {
      const form = this;
      const $form = $(form);
      if ($form.find('[' + CREATE_ACTION_ATTR + ']').length) {
        return;
      }

      const $project = $form.find('select[name="projects_id"]').first();
      const $row = $form.children('.d-flex').first();
      if (!$project.length || !$row.length || ticketId(form) <= 0) {
        return;
      }

      const $container = $('<div class="col-auto ms-4"></div>');
      const $button = $(
        '<button type="button" class="btn btn-primary" ' + CREATE_ACTION_ATTR + '>' +
          '<i class="ti ti-plus"></i> ' +
          '<span>Créer une tâche de projet</span>' +
        '</button>'
      );
      $container.append($button);
      $row.append($container);

      syncButton(form, $button.get(0));
      $project.off('change.ptdTicketProjectTask').on('change.ptdTicketProjectTask', function () {
        syncButton(form, $button.get(0));
      });

      $button.on('click.ptdTicketProjectTask', function () {
        openCreateModal(form).catch(function () {
          showLoadError();
        });
      });
    });
  }

  $(document).on('submit', '#ptd-ticket-project-task-form', async function (event) {
    event.preventDefault();

    const form = this;
    const $form = $(form);
    const $submit = $form.find('[data-ptd-create-submit]');
    const $error = $form.find('[data-ptd-create-error]');
    $error.addClass('d-none').text('');
    $submit.prop('disabled', true);

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'La création de la tâche a échoué.');
      }

      if (Array.isArray(payload.warnings) && payload.warnings.length > 0) {
        window.alert(
          'La tâche a bien été créée, mais certaines actions complémentaires ont échoué :\n\n- ' +
          payload.warnings.join('\n- ')
        );
      }

      const modalElement = document.getElementById(MODAL_ID);
      const modal = modalElement ? bootstrap.Modal.getInstance(modalElement) : null;
      if (modal) {
        modal.hide();
      }

      if (payload.ticket_closed || typeof window.reloadTab !== 'function') {
        window.location.reload();
      } else {
        window.reloadTab('');
      }
    } catch (error) {
      $error.removeClass('d-none').text(error.message || 'La création de la tâche a échoué.');
      $submit.prop('disabled', false);
    }
  });

  $(document).on('glpi.tab.loaded.projecttaskdashboard-ticketcreate', function () {
    injectTicketProjectTaskCreateAction(document);
  });

  $(function () {
    injectTicketProjectTaskCreateAction(document);
  });
})(jQuery);
