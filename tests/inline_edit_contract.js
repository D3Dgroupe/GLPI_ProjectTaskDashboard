// Issue #21: inline edit wiring in the dashboard / "Mes tâches" tables.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const fail = (msg) => { console.error('FAIL: ' + msg); process.exit(1); };

const js = read('js/projecttaskdashboard.js');
if (js !== read('public/js/projecttaskdashboard.js')) fail('root/public JS copies must stay identical');

for (const [file, needle] of [
  ['src/DashboardRenderer.php', 'data-ptd-inline-edit-url'],
  ['src/DashboardRenderer.php', 'data-ptd-project-id'],
  ['src/MyTasksRenderer.php', 'data-ptd-inline-edit-url'],
  ['templates/dashboard/widgets.html.twig', 'data-ptd-widget-key="{{ item.key }}"'],
  ['js/projecttaskdashboard.js', "'X-Glpi-Csrf-Token': csrfToken()"],
  ['js/projecttaskdashboard.js', "'X-Requested-With': 'XMLHttpRequest'"],
  ['js/projecttaskdashboard.js', 'jsClass.view.refreshResults()'],
]) {
  if (!read(file).includes(needle)) fail(`${file} must contain ${needle}`);
}

const $stub = function () { return { on() { return this; } }; };
const sandbox = { window: {}, document: {}, jQuery: $stub, URL };
vm.createContext(sandbox);
vm.runInContext(js, sandbox);
const api = sandbox.window.ProjectTaskDashboardInlineEdit;
if (!api) fail('JS must expose ProjectTaskDashboardInlineEdit');

if (JSON.stringify(api.fields) !== JSON.stringify({ '5': 'percent_done', '8': 'plan_end_date', '12': 'projectstates_id', '14': 'projecttasktypes_id' })) {
  fail('editable columns must be État (12), Type (14), % effectué (5), Date de fin planifiée (8)');
}
if (api.toDatetimeLocal('2026-09-30 18:00:00') !== '2026-09-30T18:00') fail('DB datetime -> datetime-local');
if (api.toDatetimeLocal(null) !== '') fail('empty date -> empty input');

const fakeRow = (checkboxName, href) => ({
  querySelector: () => (checkboxName ? { getAttribute: () => checkboxName } : null),
  querySelectorAll: () => (href ? [{ getAttribute: () => href }] : []),
});
if (api.taskIdFromRow(fakeRow('item[ProjectTask][42]', null)) !== 42) fail('task id from massive action checkbox');
if (api.taskIdFromRow(fakeRow(null, '/front/projecttask.form.php?id=7')) !== 7) fail('task id from task link fallback');
if (api.taskIdFromRow(fakeRow(null, null)) !== null) fail('no id -> not editable');

console.log('inline edit contract ok');
