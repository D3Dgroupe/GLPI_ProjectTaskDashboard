// Issue #22: tasks opened from the dashboard / "Mes tâches" tables open in a
// modal (GLPI's own _in_modal task form in an iframe) instead of navigating
// away, so the list position is kept.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const fail = (msg) => { console.error('FAIL: ' + msg); process.exit(1); };

const js = read('js/projecttaskdashboard.js');
if (js !== read('public/js/projecttaskdashboard.js')) fail('root/public JS copies must stay identical');
const css = read('css/projecttaskdashboard.css');
if (css !== read('public/css/projecttaskdashboard.css')) fail('root/public CSS copies must stay identical');

for (const needle of [
  'Ouvrir la fiche complète',
  'event.ctrlKey || event.metaKey',
  'if (loads > 1)',
  'refreshDashboardTable(root, table)',
  // Saving answers with Html::back() (same form reloaded in the iframe):
  // a submitted form that comes back without error closes the modal.
  "doc.addEventListener('submit'",
  'if (!taskModalHasProblem(doc))',
  'bsModal.hide()',
  '.toast-header.bg-danger',
  'window.initMessagesAfterRedirectToasts()',
  // Issue #22 follow-up: the form must not touch the modal edges.
  '<div class="modal-body px-3 py-2">',
]) {
  if (!js.includes(needle)) fail(`JS must contain ${needle}`);
}
if (!css.includes('#ptd-task-modal .ptd-task-modal-frame')) fail('CSS must size the modal iframe');

const $stub = function () { return { on() { return this; } }; };
const sandbox = { window: { location: { origin: 'https://glpi.test' } }, document: {}, jQuery: $stub, URL };
vm.createContext(sandbox);
vm.runInContext(js, sandbox);
const api = sandbox.window.ProjectTaskDashboardTaskModal;
if (!api) fail('JS must expose ProjectTaskDashboardTaskModal');

const endpoint = '/plugins/projecttaskdashboard/front/task-inline-edit.php';
const u = api.url('/front/projecttask.form.php?id=42&forcetab=ProjectTask$main', endpoint);
if (u !== 'https://glpi.test/plugins/projecttaskdashboard/front/task-modal.php?id=42') fail('modal url: ' + u);
if (api.url('/front/project.form.php?id=3', endpoint) !== null) fail('only task links open in the modal');
if (api.url('/front/projecttask.form.php', endpoint) !== null) fail('a task link without id is left alone');

// GLPI's _in_modal task form never opens its <form> for an existing task
// (buttons do nothing): the modal uses the plugin page rendering the native
// form without _in_modal.
if (js.includes("set('_in_modal'")) fail('the modal must not use projecttask.form.php?_in_modal=1');
const page = read('front/task-modal.php');
for (const needle of ['Session::checkCentralAccess()', 'Html::popHeader(', '->showForm($taskId)', 'Html::popFooter()']) {
  if (!page.includes(needle)) fail(`front/task-modal.php must contain ${needle}`);
}
if (/_in_modal['"]\]\s*=/.test(page)) fail('front/task-modal.php must not set _in_modal');

console.log('task modal contract ok');
