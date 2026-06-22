# Package Comparison

The Laravel ecosystem has a few excellent packages for handling API queries. The most prominent is `spatie/laravel-query-builder`. While it is a fantastic tool, `victormgomes/query-params` was built from the ground up to solve the specific pain points of scale, security, and boilerplate that arise in enterprise applications.

Here is a conceptual and architectural comparison between the two.

## 1. Schema Awareness & Validation (The Game Changer)

**Spatie Query Builder:**
You must manually define every allowed filter, sort, and include on every endpoint. It does not validate the URL values against your database types. If a user passes `?filter[id]=string`, it might hit the database and cause a PDO exception.

```php
// Spatie approach: Manual and repetitive
QueryBuilder::for(User::class)
    ->allowedFilters(['name', 'email', 'status', 'created_at'])
    ->allowedSorts(['name', 'created_at'])
    ->allowedIncludes(['posts'])
    ->get();
```

**victormgomes/query-params:**
It is a **Schema-Aware Translation Layer**. It automatically inspects your Eloquent model, discovers the column types, and generates native Laravel validation rules. It securely casts values (e.g., converting a string `"true"` to a boolean `true`, or `"100"` to an integer `100`) _before_ they ever hit Eloquent.

There is **zero boilerplate**. You don't need to list your columns; the package already knows them, and it fully respects your model's `$visible` and `$hidden` arrays.

```php
// Our approach: Zero boilerplate, fully validated
User::paginateQuery($request);
```

## 2. Advanced Operators & Syntax

**Spatie Query Builder:**
Out of the box, it supports simple equality and comma-separated "in" clauses. For anything more complex (like `like`, `gt`, `lt`, or OR/AND groupings), you must write and maintain custom Filter classes for every endpoint.

**victormgomes/query-params:**
Ships with an exhaustive list of universal operators (`eq`, `like`, `gt`, `lt`, `in`, `contains`, `exists`, `notnull`, etc.) and advanced operators like Full-Text Search (`fts`) and Date extraction (`year`, `month`). It also natively supports deeply nested `AND` / `OR` logic groups via the URL.

Furthermore, it supports two URL syntaxes: standard PHP array brackets (`?filters[name][like]=John`) and raw JSON strings (`?filters={"name":{"like":"John"}}`), making frontend integration seamless.

## 3. Frontend & OpenAPI Integration

**Spatie Query Builder:**
Because rules are defined inline inside the controller, there is no easy way to export what filters an endpoint accepts. Your frontend developers have to guess or read the backend source code.

**victormgomes/query-params:**
Because the schema is centralized around the Model and `#[QueryOptions]`, you can export the deduplicated schema instantly. This is perfect for dynamically rendering UI filters or generating OpenAPI/Swagger documentation.

```php
// Returns exactly what the frontend is allowed to do
$schema = User::getFilterSchema();
```

## 4. Performance

**Spatie Query Builder:**
Very fast, as it just appends constraints to the builder.

**victormgomes/query-params:**
Equally fast. While schema introspection sounds heavy, the package includes a highly optimized caching layer (`php artisan query-params:cache`). In production, it reads the exact validation rules from cache in milliseconds, resulting in zero overhead while maintaining absolute type-safety.
