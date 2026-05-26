<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\JetstreamServiceProvider::class,

    // Phase 4: registered explicitly because subscriber wiring is now
    // active in production. Before Phase 4 this provider was structurally
    // broken AND not loaded; the dead pipeline meant domain events
    // accumulated as 'pending' or were marked 'skipped' with no
    // subscribers. Phase 4 fixes both ends.
    App\Providers\FinanceServiceProvider::class,
];
