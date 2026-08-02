<?php

/*
 * Deliberately empty.
 *
 * The Filament panel is mounted at the root of this application (see
 * AdminPanelProvider), so "/" is its dashboard and the welcome page that used to
 * live here would have raced it for the same URL. Every screen the ERP has is a
 * panel page; if a public, unauthenticated route is ever needed, remember that
 * this application answers on /erp and only behind a login.
 */
