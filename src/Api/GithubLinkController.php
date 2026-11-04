<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Api;

use CommonDBTM;
use Glpi\Api\HL\Controller\AbstractController;
use Glpi\Api\HL\Doc as Doc;
use Glpi\Api\HL\Route;
use Glpi\Api\HL\RouteVersion;
use Glpi\Http\JSONResponse;
use Glpi\Http\Request;
use Glpi\Http\Response;
use GlpiPlugin\Projecttaskdashboard\Github\GithubLink;
use GlpiPlugin\Projecttaskdashboard\Github\GithubLinkValidator;
use Project;
use ProjectTask;

/**
 * High-Level API (v2) routes of the plugin, registered through Hooks::API_CONTROLLERS.
 *
 * Routes (all authenticated by the normal API v2 OAuth flow, scope "api"):
 *   GET /Plugin/Projecttaskdashboard/Status      plugin detection (version only, no data)
 *   GET /Plugin/Projecttaskdashboard/GithubLink  read links (exactly one filter family required)
 *   PUT /Plugin/Projecttaskdashboard/GithubLink  idempotent create-or-update of an item's link
 * There is deliberately no DELETE route (links are removed only when the item is purged).
 *
 * RouteVersion is 2.0: the plugin supports every GLPI 11.0.x, whose HL API starts at 2.0, so the
 * routes are reachable under /api.php/v2, /v2.0 ... /v2.3 (production) and later 2.x.
 */
#[Route(path: '/Plugin/Projecttaskdashboard', tags: ['Project Task Dashboard'])]
final class GithubLinkController extends AbstractController
{
    public const ERROR_BAD_REQUEST = 'ERROR_BAD_REQUEST';
    public const ERROR_CONFLICT = 'ERROR_CONFLICT';
    private const MAX_ITEMS_IDS = 1000;
    private const MAX_INT32 = 2147483647;
    /** @var array<string, class-string<CommonDBTM>> */
    private const ITEM_CLASSES = ['ProjectTask' => ProjectTask::class, 'Project' => Project::class];

    protected static function getRawKnownSchemas(): array
    {
        $link = self::linkSchema();
        $result = $link;
        $result['description'] = 'GithubLink returned by PUT, with created=true when the link was just created';
        $result['properties']['created'] = ['type' => Doc\Schema::TYPE_BOOLEAN];
        return ['GithubLink' => $link, 'GithubLinkWriteResult' => $result];
    }

    /** @return array<string, mixed> */
    private static function linkSchema(): array
    {
        return [
            'x-version-introduced' => '2.0',
            'type' => Doc\Schema::TYPE_OBJECT,
            'description' => 'Link between a GLPI project task (GitHub issue) or project (GitHub repository) and its GitHub object',
            'properties' => [
                'id' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64, 'readOnly' => true],
                'itemtype' => ['type' => Doc\Schema::TYPE_STRING, 'enum' => ['ProjectTask', 'Project']],
                'items_id' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64],
                'kind' => ['type' => Doc\Schema::TYPE_STRING, 'enum' => ['issue', 'repository'], 'description' => 'issue for ProjectTask, repository for Project'],
                'github_id' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT64, 'description' => 'GitHub database id (64 bits)'],
                'repository' => ['type' => Doc\Schema::TYPE_STRING, 'description' => 'owner/name'],
                'number' => ['type' => Doc\Schema::TYPE_INTEGER, 'format' => Doc\Schema::FORMAT_INTEGER_INT32, 'nullable' => true, 'description' => 'Issue number (null for a repository)'],
                'url' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_URI, 'description' => 'Must start with https://github.com/'],
                'date_creation' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME, 'readOnly' => true],
                'date_mod' => ['type' => Doc\Schema::TYPE_STRING, 'format' => Doc\Schema::FORMAT_STRING_DATE_TIME, 'readOnly' => true],
            ],
        ];
    }

    #[Route(path: '/Status', methods: ['GET'])]
    #[RouteVersion(introduced: '2.0')]
    #[Doc\Route(
        description: 'Plugin detection: version of the Project Task Dashboard plugin and supported features. Returns no business data.',
        responses: [
            new Doc\Response(
                schema: new Doc\Schema(type: Doc\Schema::TYPE_OBJECT, properties: [
                    'plugin' => new Doc\Schema(type: Doc\Schema::TYPE_STRING),
                    'version' => new Doc\Schema(type: Doc\Schema::TYPE_STRING),
                    'github_link' => new Doc\Schema(type: Doc\Schema::TYPE_BOOLEAN),
                ]),
                description: 'Plugin status'
            ),
        ]
    )]
    public function getStatus(Request $request): Response
    {
        return new JSONResponse([
            'plugin' => 'projecttaskdashboard',
            'version' => \defined('PLUGIN_PROJECTTASKDASHBOARD_VERSION') ? PLUGIN_PROJECTTASKDASHBOARD_VERSION : '',
            'github_link' => true,
        ]);
    }

    #[Route(path: '/GithubLink', methods: ['GET'])]
    #[RouteVersion(introduced: '2.0')]
    #[Doc\Route(
        description: 'List GitHub links. Exactly one filter is required (400 missing_filter otherwise, the table is never dumped): '
            . 'itemtype + items_id, itemtype + items_ids (comma separated, max 1000), kind + github_id, or project_id '
            . '(links of the tasks of that project). Links of GLPI items the caller cannot view are omitted. '
            . 'Errors: 400 {"status":"ERROR_BAD_REQUEST","code":...} with code missing_filter, conflicting_filters, '
            . 'invalid_itemtype, invalid_items_id, invalid_kind, invalid_github_id, invalid_project_id, too_many_ids.',
        parameters: [
            new Doc\Parameter(name: 'itemtype', schema: new Doc\Schema(type: Doc\Schema::TYPE_STRING, enum: ['ProjectTask', 'Project']), description: 'GLPI item type (with items_id or items_ids)'),
            new Doc\Parameter(name: 'items_id', schema: new Doc\Schema(type: Doc\Schema::TYPE_INTEGER), description: 'GLPI item id'),
            new Doc\Parameter(name: 'items_ids', schema: new Doc\Schema(type: Doc\Schema::TYPE_STRING), description: 'Comma separated GLPI item ids', example: '12,13,14'),
            new Doc\Parameter(name: 'kind', schema: new Doc\Schema(type: Doc\Schema::TYPE_STRING, enum: ['issue', 'repository']), description: 'GitHub object kind (with github_id)'),
            new Doc\Parameter(name: 'github_id', schema: new Doc\Schema(type: Doc\Schema::TYPE_INTEGER, format: Doc\Schema::FORMAT_INTEGER_INT64), description: 'GitHub database id'),
            new Doc\Parameter(name: 'project_id', schema: new Doc\Schema(type: Doc\Schema::TYPE_INTEGER), description: 'GLPI project id: links of its tasks'),
        ],
        responses: [
            new Doc\Response(
                schema: new Doc\SchemaReference('GithubLink[]'),
                description: 'Matching links (possibly empty)'
            ),
        ]
    )]
    public function search(Request $request): Response
    {
        $p = static fn(string $name): ?string => $request->hasParameter($name) && $request->getParameter($name) !== ''
            && is_scalar($request->getParameter($name)) ? trim((string) $request->getParameter($name)) : null;
        $families = array_filter([
            'item' => $p('items_id') !== null,
            'items' => $p('items_ids') !== null,
            'github' => $p('kind') !== null || $p('github_id') !== null,
            'project' => $p('project_id') !== null,
        ]);
        if ($families === []) {
            return self::badRequest('missing_filter');
        }
        if (count($families) > 1) {
            return self::badRequest('conflicting_filters');
        }

        $family = array_key_first($families);
        if ($family === 'project') {
            $projectId = self::parseId($p('project_id'));
            if ($projectId === null) {
                return self::badRequest('invalid_project_id');
            }
            // Loads the project's tasks, then linksResponse() filters each link with can(READ): N item reads per
            // call (one per linked task). Acceptable at current volumes (~100 links per project / repository).
            $taskIds = array_map(
                static fn(array $row): int => (int) $row['id'],
                array_values((new ProjectTask())->find(['projects_id' => $projectId]))
            );
            return self::linksResponse(array_values(GithubLink::findForItems('ProjectTask', $taskIds)));
        }
        if ($family === 'github') {
            $kind = (string) $p('kind');
            if (!in_array($kind, GithubLinkValidator::KIND_BY_ITEMTYPE, true)) {
                return self::badRequest('invalid_kind');
            }
            $githubId = $p('github_id');
            if ($githubId === null || preg_match('/^[1-9][0-9]{0,18}$/', $githubId) !== 1 || (string) (int) $githubId !== $githubId) {
                return self::badRequest('invalid_github_id');
            }
            $row = GithubLink::findByGithub($kind, (int) $githubId);
            return self::linksResponse($row === null ? [] : [$row]);
        }

        $itemtype = (string) $p('itemtype');
        if (!isset(self::ITEM_CLASSES[$itemtype])) {
            return self::badRequest('invalid_itemtype');
        }
        if ($family === 'item') {
            $id = self::parseId($p('items_id'));
            if ($id === null) {
                return self::badRequest('invalid_items_id');
            }
            $ids = [$id];
        } else {
            // Bounded split: at most MAX_ITEMS_IDS + 2 pieces are ever built (the last one holds the unsplit rest).
            $pieces = explode(',', (string) $p('items_ids'), self::MAX_ITEMS_IDS + 2);
            if (count($pieces) > self::MAX_ITEMS_IDS) {
                return self::badRequest('too_many_ids');
            }
            $ids = [];
            foreach ($pieces as $raw) {
                $id = self::parseId(trim($raw));
                if ($id === null) {
                    return self::badRequest('invalid_items_id');
                }
                $ids[$id] = $id;
            }
        }
        return self::linksResponse(array_values(GithubLink::findForItems($itemtype, array_values($ids))));
    }

    #[Route(path: '/GithubLink', methods: ['PUT'])]
    #[RouteVersion(introduced: '2.0')]
    #[Doc\Route(
        description: 'Create or update (idempotent) the GitHub link of a GLPI item. Requires the right to update the item '
            . '(native rights and entities). An item that does not exist or that the caller cannot view gets the same 404 '
            . '(no existence oracle); a viewable but not updatable item gets 403; an item in the trash gets 409 item_in_trash. '
            . 'A GitHub id already linked to another item (409 github_id_already_linked) or an item already linked to '
            . 'another GitHub id (409 item_linked_to_other_github_id) is refused, never overwritten. Only repository, '
            . 'number and url of an existing link are updated (repository rename). Response: the link plus "created". '
            . 'Validation errors: 400 {"status":"ERROR_BAD_REQUEST","code":...} with code invalid_itemtype, kind_mismatch, '
            . 'invalid_items_id, invalid_github_id, invalid_repo, invalid_number, invalid_url.',
        parameters: [
            new Doc\Parameter(name: 'itemtype', schema: new Doc\Schema(type: Doc\Schema::TYPE_STRING, enum: ['ProjectTask', 'Project']), location: Doc\Parameter::LOCATION_BODY),
            new Doc\Parameter(name: 'items_id', schema: new Doc\Schema(type: Doc\Schema::TYPE_INTEGER), location: Doc\Parameter::LOCATION_BODY),
            new Doc\Parameter(name: 'kind', schema: new Doc\Schema(type: Doc\Schema::TYPE_STRING, enum: ['issue', 'repository']), description: 'issue for ProjectTask, repository for Project', location: Doc\Parameter::LOCATION_BODY),
            new Doc\Parameter(name: 'github_id', schema: new Doc\Schema(type: Doc\Schema::TYPE_INTEGER, format: Doc\Schema::FORMAT_INTEGER_INT64), location: Doc\Parameter::LOCATION_BODY),
            new Doc\Parameter(name: 'repository', schema: new Doc\Schema(type: Doc\Schema::TYPE_STRING), description: 'owner/name', location: Doc\Parameter::LOCATION_BODY),
            new Doc\Parameter(name: 'number', schema: new Doc\Schema(type: Doc\Schema::TYPE_INTEGER), description: 'Issue number (required for an issue, absent/null for a repository)', location: Doc\Parameter::LOCATION_BODY),
            new Doc\Parameter(name: 'url', schema: new Doc\Schema(type: Doc\Schema::TYPE_STRING, format: Doc\Schema::FORMAT_STRING_URI), description: 'https://github.com/...', location: Doc\Parameter::LOCATION_BODY),
        ],
        responses: [
            new Doc\Response(
                schema: new Doc\SchemaReference('GithubLinkWriteResult'),
                description: 'The link, with created=true if it was just created'
            ),
            new Doc\Response(schema: null, description: 'Link conflict or item in the trash (code in the body)', status_code: 409),
        ]
    )]
    public function upsert(Request $request): Response
    {
        $input = [];
        foreach (['itemtype', 'items_id', 'kind', 'github_id', 'repository', 'number', 'url'] as $field) {
            $input[$field] = $request->hasParameter($field) ? $request->getParameter($field) : null;
        }
        try {
            $validated = GithubLinkValidator::validate($input);
        } catch (\InvalidArgumentException $e) {
            return self::badRequest($e->getMessage());
        }

        $class = self::ITEM_CLASSES[$validated['itemtype']];
        $item = new $class();
        // Not found and not viewable answer the same 404, so a caller cannot probe which ids exist.
        if (!$item->getFromDB($validated['items_id']) || !$item->can($validated['items_id'], READ)) {
            return new JSONResponse(self::getErrorResponseBody(self::ERROR_ITEM_NOT_FOUND, 'Not found') + ['code' => 'item_not_found'], 404);
        }
        if (!$item->can($validated['items_id'], UPDATE)) {
            return new JSONResponse(
                self::getErrorResponseBody(self::ERROR_RIGHT_MISSING, "You don't have permission to perform this action.") + ['code' => 'forbidden'],
                403
            );
        }
        if ($item->isDeleted()) {
            return self::conflict('item_in_trash');
        }

        try {
            $result = GithubLink::upsertForItem($validated);
        } catch (\DomainException $e) {
            return self::conflict($e->getMessage());
        }
        return new JSONResponse(self::formatLink($result['row']) + ['created' => $result['created']]);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private static function linksResponse(array $rows): Response
    {
        $visible = [];
        foreach ($rows as $row) {
            $class = self::ITEM_CLASSES[(string) $row['itemtype']] ?? null;
            if ($class !== null && (new $class())->can((int) $row['items_id'], READ)) {
                $visible[] = self::formatLink($row);
            }
        }
        usort($visible, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
        return new JSONResponse($visible);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function formatLink(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'itemtype' => (string) $row['itemtype'],
            'items_id' => (int) $row['items_id'],
            'kind' => (string) $row['kind'],
            'github_id' => (int) $row['github_id'],
            'repository' => (string) $row['repository'],
            'number' => $row['number'] === null ? null : (int) $row['number'],
            'url' => (string) $row['url'],
            'date_creation' => self::formatDate($row['date_creation'] ?? null),
            'date_mod' => self::formatDate($row['date_mod'] ?? null),
        ];
    }

    private static function formatDate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format(DATE_RFC3339);
        } catch (\Exception) {
            return null;
        }
    }

    private static function parseId(?string $raw): ?int
    {
        if ($raw === null || preg_match('/^[1-9][0-9]{0,9}$/', $raw) !== 1 || (int) $raw > self::MAX_INT32) {
            return null;
        }
        return (int) $raw;
    }

    private static function badRequest(string $code): Response
    {
        return new JSONResponse(self::getErrorResponseBody(self::ERROR_BAD_REQUEST, 'Bad request', $code) + ['code' => $code], 400);
    }

    private static function conflict(string $code): Response
    {
        return new JSONResponse(self::getErrorResponseBody(self::ERROR_CONFLICT, 'Conflict', $code) + ['code' => $code], 409);
    }
}
