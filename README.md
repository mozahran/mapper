# zahran/mapper

Compile a JSON mapping template once, map many payloads with it.

A mapping template is data, not code: it says where each output field comes from, how to
narrow and order lists, and how to transform values on the way out. Templates are
validated when they compile, so a broken template fails once, up front, with the path to
the problem — never silently, one payload at a time.

```php
use Zahran\Mapper\V2\Mapper;

$mapper  = Mapper::default();
$mapping = $mapper->compile($template);   // validate once
$mapping->map($payload);                  // reuse for every payload
```

- [Installation](#installation)
- [A first mapping](#a-first-mapping)
- [The template](#the-template)
- [Paths](#paths) · [Gathering several paths](#gathering-several-paths)
- [Lists](#lists) · [Narrowing a list](#narrowing-a-list)
- [The pipeline](#the-pipeline) · [Conditions](#conditions) · [Mutators](#mutators) · [Casts](#casts)
- [The shape of the result](#the-shape-of-the-result)
- [Validating the payload](#validating-the-payload) · [Strict mapping](#strict-mapping) · [Errors](#errors)
- [Extending the mapper](#extending-the-mapper)
- [Reusing a compiled mapping](#reusing-a-compiled-mapping)

## Installation

```bash
composer require zahran/mapper
```

Requires PHP 8.0 or newer and `ext-json`. Tested on 8.0 through 8.5.

## A first mapping

```php
use Zahran\Mapper\V2\Mapper;

$payload = '{
    "order_id": 91,
    "customer": {"first": "Ada", "last": "Lovelace"},
    "lines": [
        {"sku": "A-1", "qty": "2", "price": "40.50"},
        {"sku": "B-7", "qty": "1", "price": "9.99"}
    ]
}';

$template = '{
    "attributes": [
        {"name": "Reference", "path": ["order_id"], "cast": {"type": "string"}},
        {
            "name": "Customer",
            "paths": [["customer", "first"], "$ ", ["customer", "last"]],
            "mutators": [{"name": "implode", "arguments": ["", "__value__"]}]
        },
        {
            "name": "Lines",
            "type": "array",
            "path": ["lines"],
            "attributes": [
                {"name": "Sku", "path": ["sku"]},
                {"name": "Quantity", "path": ["qty"], "cast": {"type": "integer"}},
                {"name": "Price", "path": ["price"], "cast": {"type": "float"}}
            ]
        }
    ]
}';

Mapper::default()->map($payload, $template);
```

```php
[
    'Reference' => '91',
    'Customer'  => 'Ada Lovelace',
    'Lines'     => [
        ['Sku' => 'A-1', 'Quantity' => 2, 'Price' => 40.5],
        ['Sku' => 'B-7', 'Quantity' => 1, 'Price' => 9.99],
    ],
]
```

Payloads and templates may be given as JSON strings or as already-decoded PHP arrays.

## The template

A template is an attribute. Most templates declare `attributes` and yield an object, but
a template that declares a `path` or a `type` of its own yields [whatever that
describes](#the-shape-of-the-result).

Each attribute takes:

| Key | Meaning |
|---|---|
| `name` | the key it writes in the output — required, non-empty |
| `path` | where to read it from, as a [list of segments](#paths) |
| `paths` | several sources [gathered into one value](#gathering-several-paths), instead of `path` |
| `default` | what to use when the payload holds nothing for it |
| `required` | reject the payload when it holds nothing for it — see [validating](#validating-the-payload) |
| `conditions` | [rewrite the value](#conditions) when it matches |
| `mutators` | [transform the value](#mutators) |
| `cast` | [convert the value](#casts) |
| `type` | `"array"` — see [lists](#lists) |
| `attributes` | the shape of each item, on an `"array"` attribute |
| `where`, `sort`, `distinct`, `offset`, `limit` | [narrow a list](#narrowing-a-list) |

Anything else is rejected when the template compiles, naming the key and where it sat:

```php
Mapper::default()->compile('{"attributes": [{"name": "A", "path": ["a"], "transform": "x"}]}');
// InvalidTemplateException: Invalid mapping template at "attributes.0":
//   "transform" is not a supported key here, expected one of: name, type, path, …
```

## Paths

A path is a list of segments walked one at a time.

### Keys and indexes

```php
// {"order": {"customer": {"name": "Ada"}}}
{"name": "Customer", "path": ["order", "customer", "name"]}   // "Ada"

// {"rows": [{"id": 7}, {"id": 8}]}
{"name": "First", "path": ["rows", 0, "id"]}                  // 7
{"name": "Last",  "path": ["rows", -1, "id"]}                 // 8
```

A negative index counts from the end of a list, but only after a literal lookup misses —
a payload that really does hold the key `-1` still reads the way it always did.

### `*` — every value one level down

```php
// {"items": [{"sku": "A"}, {"sku": "B"}]}
{"name": "Skus", "path": ["items", "*", "sku"]}      // ["A", "B"]

// {"prices": {"skirt": 40, "shirt": 30}}
{"name": "Prices", "path": ["prices", "*"]}          // [40, 30]
```

Wildcards nest: `["orders", "*", "lines", "*", "sku"]` reads every sku of every line of
every order. An element that lacks the key contributes nothing rather than a null.

### `**` — every descendant

```php
// {"order": {"lines": [{"sku": "A"}], "gift": {"sku": "B"}}}
{"name": "Skus", "path": ["**", "sku"]}              // ["A", "B"]
```

`**` matches the scope it stands on and everything nested anywhere beneath it, so
`["kept", "**", "id"]` searches only under `kept`.

### `{"where": …}` — every child that matches

```php
// {"items": [{"sku": "A", "active": true}, {"sku": "B", "active": false}]}
{
    "name": "Skus",
    "path": ["items", {"where": {"path": ["active"], "condition_type": "eq", "value": true}}, "sku"]
}
// ["A"]
```

A clause reads `path` (optional — without it the element itself is tested),
`condition_type` (any [registered condition](#conditions)) and `value`. Give `where` a
list of clauses and every one must hold:

```php
{"where": [
    {"path": ["active"], "condition_type": "eq", "value": true},
    {"path": ["stock"],  "condition_type": "gt", "value": 0}
]}
```

A key the element lacks tests as `null`, so `notnull` says what you would expect about a
key that is not there at all.

### `@` — the element itself

Inside a list of scalars there is no key to reach the element by:

```php
// {"scores": [10, 55, 3]}
{
    "name": "Scores",
    "type": "array",
    "path": ["scores"],
    "where": {"condition_type": "gte", "value": 10},
    "attributes": [{"name": "Value", "path": ["@"]}]
}
// [{"Value": 10}, {"Value": 55}]
```

### Collection-shaped paths

A path holding `*`, `**` or a filter resolves to the list of every match — and to an
empty list when nothing matched, rather than to null:

```php
// {}
{"name": "Skus", "path": ["items", "*", "sku"]}                     // []
{"name": "Skus", "path": ["items", "*", "sku"], "default": "none"}  // "none"
```

### Picking positions

A trailing list of indexes reads fixed positions out of whatever the path resolved to.
`$`-prefixed entries are hard-coded values rather than positions:

```php
// {"categories": [10, 55, 3, 20]}
{"name": "Categories", "path": ["categories", [0, 1]]}
// [10, 55]

// {"categories": [10, 55]}
{"name": "Categories", "path": ["categories", [0, "$fixed", "$7", "$true", "$null"]]}
// [10, "fixed", 7, true, null]
```

A position the payload has nothing at reads as null, so the result always has as many
entries as the template asked for. To take the first three items of a list *and have the
result shorten when there are fewer*, use [`limit`](#narrowing-a-list) instead.

### When the payload holds nothing

```php
// {}
{"name": "Name", "path": ["fullname"], "default": "John Doe"}   // "John Doe"

// {"value": null}
{"name": "Value", "path": ["value"], "default": "fallback"}     // null
```

An explicit null is a value; only a key that is not there at all falls back on the
default. An attribute with no `path` at all is a hard-coded value:

```php
{"name": "Source", "default": "static"}
{"name": "Flags",  "default": ["$true", "$null", "$7", "plain"]}   // [true, null, 7, "plain"]
```

## Gathering several paths

`paths` reads several sources into one list, so a mutator can combine them:

```php
// {"first": "Ada", "last": "Lovelace"}
{
    "name": "Name",
    "paths": [["first"], "$ ", ["last"]],
    "mutators": [{"name": "implode", "arguments": ["", "__value__"]}]
}
// "Ada Lovelace"

// {"net": 40, "tax": 8}
{"name": "Total", "paths": [["net"], ["tax"]], "mutators": [{"name": "array_sum"}]}
// 48
```

Without a mutator the gathered list is the value: `{"name": "Both", "paths": [["a"], ["b"]]}`
yields `[1, 2]`. A `$`-prefixed entry is a hard-coded value sitting between the paths.

A path the payload has no value for contributes null and keeps the others in place
(`["Ada", null]`). Only when *every* path is missing does the attribute fall back on its
`default`.

Values gathered this way are piped whole rather than one element at a time, which is what
lets `implode` and `array_sum` see the lot.

## Lists

An attribute of `"type": "array"` maps each element of the array its path resolves to,
using its own `attributes` as the shape of each item:

```php
// {"items": [{"name": "Skirt"}, {"name": "Shirt"}]}
{
    "name": "Items",
    "type": "array",
    "path": ["items"],
    "attributes": [{"name": "ItemName", "path": ["name"]}]
}
// [{"ItemName": "Skirt"}, {"ItemName": "Shirt"}]
```

Lists nest to any depth, and each level's paths are relative to its own element:

```php
// {"orders": [{"id": 1, "lines": [{"sku": "A"}, {"sku": "B"}]}]}
{
    "name": "Orders",
    "type": "array",
    "path": ["orders"],
    "attributes": [
        {"name": "Id", "path": ["id"]},
        {
            "name": "Lines",
            "type": "array",
            "path": ["lines"],
            "attributes": [{"name": "Sku", "path": ["sku"]}]
        }
    ]
}
// [{"Id": 1, "Lines": [{"Sku": "A"}, {"Sku": "B"}]}]
```

A list whose source is absent, or is not an array, yields `[]` — unless the attribute is
[`required`, or the mapper is strict](#validating-the-payload). A list attribute may omit
its `path` to map the scope it is already standing on. `default`, `cast`, `conditions`
and `mutators` belong on the nested attributes, not on the list itself.

## Narrowing a list

`where`, `sort`, `distinct`, `offset` and `limit` decide which elements are mapped and in
what order. They run in that order, and they run *before* anything is mapped, so a limit
caps the work as well as the output.

Given `{"items": [{"sku": "A", "price": 30, "active": true}, {"sku": "B", "price": 10,
"active": false}, {"sku": "C", "price": 20, "active": true}]}`:

```php
"where": {"path": ["active"], "condition_type": "eq", "value": true}   // A, C
"sort":  {"path": ["price"]}                                          // B, C, A
"sort":  {"path": ["price"], "direction": "desc"}                     // A, C, B
"limit": 2                                                            // A, B
"offset": 1, "limit": 1                                               // B
"offset": -1                                                          // C
```

All five together read as you would expect — keep the active ones, dearest first, take
one:

```php
{
    "name": "Items",
    "type": "array",
    "path": ["items"],
    "where": {"path": ["active"], "condition_type": "eq", "value": true},
    "sort":  {"path": ["price"], "direction": "desc"},
    "limit": 1,
    "attributes": [{"name": "Sku", "path": ["sku"]}]
}
// [{"Sku": "A"}]
```

**`where`** takes the same clauses as a [filter segment](#paths):
one clause, or a list of clauses that must all hold.

**`sort`** takes one sort key or a list of them, weighed in turn so the second only
speaks where the first ties. `direction` is `"asc"` (the default) or `"desc"`, and `path`
is optional — without it the element itself is compared. Elements the payload has no
value for gather at one end rather than scattering, and elements that tie on every key
keep the order the payload gave them.

```php
"sort": [{"path": ["group"]}, {"path": ["price"]}]
```

**`distinct`** keeps the first of each group. Give it the path — or paths — that identify
an element, or `true` to compare whole elements. Identity is exact, so `1` and `"1"` stay
apart.

```php
// {"items": [{"sku": "A", "price": 1}, {"sku": "A", "price": 9}, {"sku": "B", "price": 2}]}
"distinct": ["sku"]        // the A at price 1, and B
"distinct": [["sku"], ["size"]]
"distinct": true
```

Because `distinct` runs after `sort`, sorting first decides *which* duplicate survives.

**`offset`** and **`limit`** take a window of what is left. `offset` may be negative to
count from the end; `limit` must be zero or more.

Narrowing works on any list, including one reached by a wildcard:

```php
// {"orders": [{"lines": [{"q": 1}, {"q": 9}]}, {"lines": [{"q": 5}]}]}
{
    "name": "Lines",
    "type": "array",
    "path": ["orders", "*", "lines", "*"],
    "where": {"path": ["q"], "condition_type": "gte", "value": 5},
    "attributes": [{"name": "Qty", "path": ["q"]}]
}
// [{"Qty": 9}, {"Qty": 5}]
```

## The pipeline

`conditions`, `mutators` and `cast` transform a value on its way out. They always run in
that order — conditions, then mutators, then the cast — however the template is written,
and each step feeds the next.

```php
// {"qty": "2"}
{
    "name": "Total",
    "path": ["qty"],
    "cast": {"type": "string"},
    "mutators": [{"name": "multiply", "arguments": [3]}],
    "conditions": [{"condition_type": "is_string", "then": 10}]
}
// "30"  — the condition saw the raw string, multiply saw 10, the cast saw 30
```

When the value is an array, each step applies to every element of it:

```php
// {"tags": ["red", "green"]}
{"name": "Tags", "path": ["tags"], "mutators": [{"name": "strtoupper"}]}
// ["RED", "GREEN"]
```

Values gathered with `paths` are the exception: they are piped whole, so that
`array_sum` and `implode` can combine them.

The pipeline is skipped entirely when the payload holds nothing for the path — the
`default` stands as written, rather than being cast or mutated.

### Conditions

A condition rewrites the value to `then` when it matches, and to `otherwise` when it does
not. Without `otherwise` a value that does not match is left alone.

```php
// {"completed": true}
{"name": "Status", "path": ["completed"],
 "conditions": [{"condition_type": "eq", "value": true, "then": "done", "otherwise": "pending"}]}
// "done"
```

Conditions run in declaration order and feed each other, so a later one sees what an
earlier one wrote:

```php
// {"score": 95}
"conditions": [
    {"condition_type": "gte", "value": 90, "then": "A"},
    {"condition_type": "eq",  "value": "A", "then": "excellent"}
]
// "excellent"
```

| `condition_type` | Matches when |
|---|---|
| `eq` | the value loosely equals `value` (`"1"` equals `1`) |
| `neq` | the value is not identical to `value` |
| `gt`, `gte`, `lt`, `lte` | the value compares that way against `value` |
| `in` | the value is in `value` — a list, or a comma-separated string |
| `not_in` | it is not |
| `contains` | every needle in `value` appears in the value, case-insensitively |
| `missing` | the negation of `contains` |
| `null`, `notnull` | the value is, or is not, null |
| `is_numeric`, `is_string`, `is_boolean`, `is_float` (`is_double`) | the value is of that type |

### Mutators

A mutator transforms the value. `arguments` is optional; when it is omitted the value is
passed as the sole argument, and when it is given the literal `"__value__"` marks where
the value goes.

```php
// {"name": "Ada"}
"mutators": [{"name": "strtoupper"}]                                        // "ADA"

// {"title": "hello world"}
"mutators": [{"name": "str_replace", "arguments": [" ", "-", "__value__"]}]  // "hello-world"

// {"views": 10}
"mutators": [{"name": "multiply", "arguments": [5]}]                         // 50
```

Mutators run in declaration order:

```php
// {"name": "  adalovelace  "}
"mutators": [
    {"name": "trim"},
    {"name": "strtoupper"},
    {"name": "substr", "arguments": ["__value__", 0, 3]}
]
// "ADA"
```

Registered mutators: `add`, `subtract`, `multiply`, `divide`, `modulo`, `power`. Each
takes one argument and coerces both sides to numbers; anything PHP would refuse to do
arithmetic on yields null rather than raising.

Beyond those, a mutator may name a PHP function — but only one on the allow list, because
a template is data and data must never reach arbitrary code. Every listed function is
side-effect free and takes no callable, so none can be used to invoke another:

```
abs array_flip array_keys array_product array_reverse array_slice array_sum array_unique
array_values base64_decode base64_encode bin2hex boolval ceil count date dechex explode
floatval floor gettype gmdate hexdec htmlspecialchars implode intdiv intval json_decode
json_encode lcfirst ltrim max mb_strlen mb_strtolower mb_strtoupper mb_substr md5 min
nl2br number_format pow preg_quote preg_replace preg_split rawurlencode round rtrim sha1
sprintf sqrt str_contains str_ends_with str_pad str_repeat str_replace str_split
str_starts_with str_word_count strip_tags strlen strrev strtolower strtotime strtoupper
strtr strval substr substr_count trim ucfirst ucwords urlencode vsprintf wordwrap
```

Anything else is refused when the template compiles:

```php
Mapper::default()->compile('{"attributes": [{"name": "A", "path": ["a"], "mutators": [{"name": "shell_exec"}]}]}');
// InvalidTemplateException: … "shell_exec" is neither a registered mutator nor an allowed PHP function.
```

Add your own with [`withMutator()` or `withFunctions()`](#extending-the-mapper).

### Casts

```php
// {"price": "40.5"}
{"name": "Price", "path": ["price"], "cast": {"type": "float"}}     // 40.5

// {"when": "2024-03-09T11:30:00+00:00"}
{"name": "Day", "path": ["when"], "cast": {"type": "date", "format": "d/m/Y"}}
// "09/03/2024"
```

| `type` | Result |
|---|---|
| `integer` | `"40"` → `40`, `"40.9"` → `40`, `null` → `0` |
| `float` | `"40.5"` → `40.5`, `40` → `40.0` |
| `string` | `40` → `"40"`, `true` → `"1"`, `false` and `null` → `""` |
| `boolean` | PHP truthiness: `"0"`, `""`, `null`, `[]` → `false` |
| `date` | reformatted with the required `format`; an empty value stays null |

`format` is required for `date`, and the template is rejected when it is absent. These
conversions are deliberately forgiving; [strict mapping](#strict-mapping) makes them
refuse values they would have to invent an answer for.

## The shape of the result

The root is an attribute like any other, so a mapping is not forced to end in an object.

```php
// {"items": [{"sku": "A"}, {"sku": "B"}]}
'{"type": "array", "path": ["items"], "attributes": [{"name": "Sku", "path": ["sku"]}]}'
// [["Sku" => "A"], ["Sku" => "B"]]

// [{"sku": "A"}, {"sku": "B"}]  — a root list with no path maps the payload itself
'{"type": "array", "attributes": [{"name": "Sku", "path": ["sku"]}]}'
// [["Sku" => "A"], ["Sku" => "B"]]

// {"order": {"id": 7}}
'{"path": ["order", "id"]}'                                  // 7

// {"order": {"total": "40.5"}}
'{"path": ["order", "total"], "cast": {"type": "float"}}'    // 40.5
```

A root list narrows with `where`, `sort` and the rest like any other, and a root value is
piped like any other. `map()` therefore returns `mixed`.

## Validating the payload

Mapping is forgiving by default: a missing path falls back on its default, and a list
whose source is not an array yields `[]`. Two things make it strict where you want it.

### `required`

```php
{"name": "Sku", "path": ["sku"], "required": true}
```

```php
Mapper::default()->map('{}', $template);
// InvalidPayloadException: Cannot map the payload at "Sku":
//   is required, but the payload holds no value for it.
```

An explicit null satisfies `required` — only absence does not. On a list attribute,
`required` also rejects a source that is not an array, but accepts an empty one. A
failure names the attribute by its full dotted path in the output:

```php
// Cannot map the payload at "Orders.Lines.Sku": is required, but the payload holds no value for it.
```

`required` cannot be combined with `default`, which already stands in for a missing
value; the template is rejected when it compiles.

### Strict mapping

```php
$mapper = Mapper::default()->strict();
```

A strict mapper refuses to invent values rather than coercing them:

```php
// {"count": "abc"}
{"name": "Count", "path": ["count"], "cast": {"type": "integer"}}

Mapper::default()->map($payload, $template);              // ['Count' => 0]
Mapper::default()->strict()->map($payload, $template);
// InvalidPayloadException: Cannot map the payload at "Count":
//   "abc" (string) cannot be cast to "integer" without losing its meaning.
```

The bar is faithfulness, not convertibility — `(bool) "false"` is `true` in PHP, and that
is exactly the silent answer strictness is there to spare you. A strict `integer` takes
an int, a bool, a whole float or a whole numeric string; a strict `boolean` takes a bool,
`0`/`1` or `"0"`/`"1"`, and refuses `"false"`.

Null is carried through casts untouched rather than becoming `0`, `""` or `false`, and a
path the payload has no value for still falls back on its `default`: absence is not an
error unless the attribute says `required`. A list attribute whose source is present but
is not a list fails instead of yielding `[]`:

```php
Mapper::default()->strict()->map('{"items": "nope"}', $template);
// InvalidPayloadException: Cannot map the payload at "Items":
//   expects a list, but the payload holds string.
```

`strict()` returns a new mapper and survives registering more conditions, casts,
mutators or functions. Custom casts stay trusted unless they implement
[`Cast\Validating`](#extending-the-mapper).

### Errors

Everything the library throws implements `Exception\MappingException`, so one catch
covers the lot.

| Exception | Extends | Thrown |
|---|---|---|
| `InvalidTemplateException` | `InvalidArgumentException` | compiling — the template is wrong |
| `InvalidJsonException` | `InvalidArgumentException` | decoding — the JSON is wrong |
| `InvalidPayloadException` | `RuntimeException` | mapping — the payload is wrong |

Whatever a cast or a mutator throws is carried out as an `InvalidPayloadException` naming
the attribute, with the original kept as `previous`:

```php
// {"when": "the day before never"} with a date cast
// InvalidPayloadException: Cannot map the payload at "When":
//   DateTimeImmutable::__construct(): Failed to parse time string …
```

## Extending the mapper

Every `with*()` method returns a new mapper and leaves the original alone.

```php
use Zahran\Mapper\V2\Condition\Predicate;

$mapper = Mapper::default()->withCondition('starts_with', new class implements Predicate {
    public function matches(mixed $value, mixed $compare): bool
    {
        return is_string($value) && is_string($compare) && str_starts_with($value, $compare);
    }
});

// {"sku": "AB-1"}
{"name": "Kind", "path": ["sku"],
 "conditions": [{"condition_type": "starts_with", "value": "AB-", "then": "internal", "otherwise": "external"}]}
// "internal"
```

Custom conditions work anywhere a condition does, including `where` clauses.

```php
use Zahran\Mapper\V2\Cast\Cast;
use Zahran\Mapper\V2\Cast\Validating;

// Implementing Validating as well is optional: it is what a strict mapper asks before
// handing the cast a value. A cast that does not implement it is trusted with anything.
$mapper = Mapper::default()->withCast('cents', new class implements Cast, Validating {
    public function cast(mixed $value, ?string $format): mixed
    {
        return (int) round(((float) $value) * 100);
    }

    public function accepts(mixed $value): bool
    {
        return is_numeric($value);
    }
});

// {"price": "40.50"} with {"cast": {"type": "cents"}}  =>  4050
```

```php
use Zahran\Mapper\V2\Mutator\Mutator;

$mapper = Mapper::default()->withMutator('suffix', new class implements Mutator {
    public function apply(mixed $value, array $arguments): mixed
    {
        return $value . ($arguments[0] ?? '');
    }
});

// {"sku": "A"} with {"mutators": [{"name": "suffix", "arguments": ["-EU"]}]}  =>  "A-EU"
```

To widen the allow list instead of writing a mutator:

```php
$mapper = Mapper::default()->withFunctions('addslashes', 'nl2br');
```

## Reusing a compiled mapping

Compiling validates the template and builds the nodes; mapping walks them. When the same
template serves many payloads, compile once:

```php
$mapping = Mapper::default()->compile($template);

$mapping->map($first);
$mapping->map($second);
```

`mapMany()` maps lazily and preserves keys — nothing is read until the generator is
consumed:

```php
$mapping->mapMany(['a' => '{"name": "Ada"}', 'b' => ['name' => 'Linus']]);
// ['a' => ['Name' => 'Ada'], 'b' => ['Name' => 'Linus']]
```

`Mapper::map($data, $template)` compiles and maps in one call, which is convenient for a
one-off and wasteful in a loop.

## What this library does not do

- **It does not map back.** The transform is lossy by construction — conditions collapse
  ranges into labels, casts discard precision, `default` erases the fact that a key was
  absent — so a round trip means writing a second template by hand.
- **It does not hydrate objects.** Nodes emit plain arrays and casts produce scalars.
  Build your DTOs from the result.

## Testing

```bash
composer install
composer test
```

Or against every supported PHP version, with Docker:

```bash
docker compose run --rm tests
```

## License

MIT.
