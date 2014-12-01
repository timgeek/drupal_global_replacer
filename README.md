drupal_global_replacer
======================

Replace text strings globally across all fields of a drupal site.

To be called as a drush script.

Syntax with included file:
drush scr /path/to/drupal_global_replacer.php --execute --file=/path/to/file.php

Note that file.php should contain an array named $patterns with search strings as keys and replacement strings as values.

Syntax with single find-replace pair:
drush scr /path/to/drupal_global_replacer.php --execute search-string replacement-string

--execute will execute the string replacement.  Otherwise, the script just tells you which nodes and fields contain the search string.

@todo: Perform search and replace on custom blocks and views.
