<?php

declare(strict_types=1);

$adapter = file_get_contents(__DIR__ . '/../src/Search/NativeSearchAdapter.php');
$scope = @file_get_contents(__DIR__ . '/../src/Search/SearchFormPreferenceScope.php');

assert($adapter !== false);
assert($scope !== false, 'SearchFormPreferenceScope must exist');
assert(str_contains($adapter, 'SearchFormPreferenceScope'), 'adapter must render inside search form preference scope');
assert(str_contains($scope, 'glpishow_search_form'), 'scope must temporarily force the full search form preference');

echo "search form scope contract ok\n";
