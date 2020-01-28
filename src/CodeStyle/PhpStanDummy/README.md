to use in phpstan.neon.dist when parent classes are not defined:

```yaml
parameters:
    autoload_files:
        - .../src/CodeStyle/PhpStanDummy/LoadFallbackClasses.php
```
