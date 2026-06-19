# Advanced Usage

## Intercepting and Modifying Rules

When your FormRequest requires custom validation alongside the package's generated rules, use the `$this->queryParamRules()` mixin method to merge them seamlessly.

```php
#[MapQueryParams(User::class)]
class IndexUserRequest extends FormRequest
{
    public function rules(): array
    {
        return array_merge(
            $this->queryParamRules(),
            [
                'custom_header' => 'required|string',
            ]
        );
    }
}
```

## Global Registry (Alternative Resolution)

If you cannot modify the FormRequest class directly (e.g., in a third-party package), register the request-model mapping globally:

```php
use Victormgomes\QueryParams\Support\ModelRegistry;

ModelRegistry::register(IndexUserRequest::class, User::class);
```

## Automatic Type Casting

Values are automatically cast into native PHP types based on your schema before hitting Eloquent.

| Database Type | Cast | Example |
| :------------ | :--- | :------ |
| `integer`, `bigint` | `(int)` | `100` |
| `float`, `decimal` | `(float)` | `9.99` |
| `boolean` | `filter_var()` | `true` |
| `date`, `datetime` | `Carbon::parse()` | `Carbon` instance |

## Frontend Dynamic Filters (Metadata)

The package provides methods to export the schema for frontend applications building dynamic filter UIs.

```php
use Victormgomes\QueryParams\Support\Resource;

// Deduplicated schema (Recommended for Frontend integration)
return Resource::getFilterSchema(User::class);

// Full exhaustive schema with all aliases and syntax mapping
return Resource::getQueryGuide(User::class);
```
