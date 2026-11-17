# Relaxa template gallery

The Relaxa starter designs are stored as organisation-scoped catalog entries. They appear in the template gallery and are copied into **Your Library** when a user selects **Use template**. Editing or deleting a library copy does not change the catalog design.

The dedicated seeder is intentionally not called by `DatabaseSeeder`. It requires an explicit organisation ID and only writes catalog entries for that organisation. The three entries use stable slugs and version 1, so rerunning the seeder updates the gallery designs without changing user-created library copies or campaign content.

## Install on the live site

From the live Laravel application directory, after deploying the code:

```bash
/opt/plesk/php/8.3/bin/php artisan migrate --force
RELAXA_ORGANISATION_ID=1 /opt/plesk/php/8.3/bin/php artisan db:seed --class='Database\Seeders\RelaxaTemplateCatalogSeeder' --force
```

Confirm the organisation ID before running the seeder. The seeder fails if the ID is missing or the organisation is not active. It does not create organisations or send email.

Once at least one catalog design exists for an organisation, its gallery shows those organisation-specific designs instead of the generic Studio collection. Existing saved templates remain in **Your Library** and can be deleted there by an authorised campaign manager. Campaigns store their own content and are not linked to a library template, so deleting a saved template leaves campaign content unchanged.

