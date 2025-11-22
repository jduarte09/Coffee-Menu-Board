How to use:

Use the Order field (right sidebar) to control the row order within a category.

Display with the shortcode

Basic (all categories, auto columns):

[coffee_menu_board title="Our Menu"]


Specific columns & order (up to 4; use slugs separated by |):

[coffee_menu_board title="Our Menu" categories="coffee|pastries|tea" columns="auto"]


Force 4 columns (even if fewer categories are selected; empty ones will still render):

[coffee_menu_board categories="coffee|pastries|tea|cold-drinks" columns="4"]


Include a column for uncategorized items:

[coffee_menu_board categories="coffee|pastries" include_uncategorized="1"]


Hide the badge:

[coffee_menu_board show_badge="0"]


Responsive behavior

Desktop: up to 4 columns in a neat grid.

Tablets: 2 columns when space gets tight.

Phones: columns stack vertically; price appears under the item for readability.
