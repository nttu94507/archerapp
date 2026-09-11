<?php

return [
    // MVP 對外驗證期間只呈現「資格賽 → 個人對抗 → 結案」主流程。
    // 關閉後即可恢復既有團體、商店、Badge 與進階入口。
    'mvp_mode' => env('MVP_MODE', env('APP_ENV', 'production') !== 'testing'),
];
