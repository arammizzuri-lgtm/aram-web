<?php

/*
 * Deliberately empty.
 *
 * The Filament panel is mounted at the root of this application (see
 * AdminPanelProvider), so "/" is its dashboard and the welcome page that used to
 * live here would have raced it for the same URL. Every screen the ERP has is a
 * panel page — including the printable invoice, which is registered on the panel
 * itself so that it inherits the panel's session and its login: a route declared
 * out here would be guarded by Laravel's stock auth middleware, which redirects
 * to a route named `login` that a Filament application does not have.
 */
