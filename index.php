<? include("php.php"); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Инвестиционный портфель | Дашборд</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script>
        // Предотвращаем конфликт MetaMask
        if (window.ethereum) {
            console.log('MetaMask detected');
        }
    </script>
</head>
<body class="<?= $current_theme === 'dark' ? 'dark-theme' : '' ?>">
    <div class="dashboard">
        <div class="notification-container" id="notificationContainer"></div>

        <!-- Модальное окно добавления нового актива -->
        <div class="modal-overlay" id="addAssetModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #ff9f4a;"></i> Добавление актива</h2>
                    <button class="modal-close" id="closeAddAssetModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addAssetForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Символ актива *</label>
                            <input type="text" class="form-input" id="newAssetSymbol" placeholder="Например: BTC, ETH, AAPL" value="" readonly style="text-transform: uppercase;">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-font"></i> Название актива *</label>
                            <input type="text" class="form-input" id="newAssetName" placeholder="Например: Bitcoin, Ethereum, Apple Inc.">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Тип актива *</label>
                            <div class="asset-type-buttons">
                                <button type="button" class="asset-type-btn" data-type="crypto">Криптовалюта</button>
                                <button type="button" class="asset-type-btn" data-type="stock">Акция</button>
                                <button type="button" class="asset-type-btn" data-type="etf">ETF</button>
                                <button type="button" class="asset-type-btn" data-type="bond">Облигация</button>
                                <button type="button" class="asset-type-btn" data-type="currency">Валюта</button>
                                <button type="button" class="asset-type-btn" data-type="other">Другое</button>
                            </div>
                            <input type="hidden" id="newAssetType" value="">
                        </div>

                        <!-- БЛОК ВЫБОРА РЫНКА (добавить после выбора типа актива) -->
                        <div class="form-group" id="marketSelectGroup" style="display: none;">
                            <label><i class="fas fa-globe"></i> Рынок *</label>
                            <div class="market-buttons">
                                <button type="button" class="market-type-btn" data-market="ru">🇷🇺 РФ (RUB)</button>
                                <button type="button" class="market-type-btn" data-market="foreign">🌍 Иностранный (USD)</button>
                            </div>
                            <input type="hidden" id="newAssetMarket" value="">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Выберите рынок для акции/ETF/облигации
                            </small>
                        </div>
                        
                        <!-- БЛОК ВЫБОРА СЕКТОРА (с отдельным классом) -->
                        <div class="form-group" id="sectorSelectGroup" style="display: none;">
                            <label><i class="fas fa-chart-line"></i> Сектор *</label>
                            <div class="sector-buttons" id="sectorButtons">
                                <?php
                                $stmt = $pdo->query("SELECT name_ru, name FROM sectors WHERE type IN ('stock', 'etf') AND is_active = 1 ORDER BY name_ru");
                                $sectors_list = $stmt->fetchAll();
                                foreach ($sectors_list as $sector):
                                ?>
                                <button type="button" class="sector-option-btn" data-sector="<?= htmlspecialchars($sector['name']) ?>">
                                    <?= htmlspecialchars($sector['name_ru']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="newAssetSector" value="">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Выберите сектор для акции/ETF
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddAssetBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddAssetBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> Сохранить актив
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно деталей актива -->
        <div class="modal-overlay" id="assetDetailsModal">
            <div class="modal" style="max-width: 650px; max-height: 80vh;">
                <div class="modal-header">
                    <h2><i class="fas fa-chart-pie" style="color: #ff9f4a;"></i> <span id="assetDetailsSymbol"></span></h2>
                    <button class="modal-close" onclick="closeAssetDetailsModal()">&times;</button>
                </div>
                <div class="modal-body" id="assetDetailsBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeAssetDetailsModal()">Закрыть</button>
                    <button class="btn btn-primary" id="showHistoryBtn" style="background: #1a5cff;" onclick="showAssetPurchaseHistory()">
                        <i class="fas fa-history"></i> Показать историю покупок
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно истории покупок (оставляем старое или обновляем) -->
        <div class="modal-overlay" id="purchaseHistoryModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-history" style="color: #1a5cff;"></i> История покупок <span id="purchaseHistorySymbol"></span></h2>
                    <button class="modal-close" onclick="closePurchaseHistoryModal()">&times;</button>
                </div>
                <div class="modal-body" id="purchaseHistoryBody">
                    <div style="text-align: center; padding: 30px;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closePurchaseHistoryModal()">Закрыть</button>
                    <button class="btn btn-primary" id="backToDistributionBtn" style="background: #ff9f4a;" onclick="closePurchaseHistoryModal(); showAssetDetails(currentAssetSymbol, currentAssetId);">
                        <i class="fas fa-arrow-left"></i> Назад к распределению
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно пополнения -->
        <div class="modal-overlay" id="depositModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #00a86b;"></i> Пополнение</h2>
                    <button class="modal-close" id="closeDepositModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="depositForm">
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Площадка *</label>
                            <button type="button" class="platform-select-btn" id="selectPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedPlatformDisplay">Выбрать площадку</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="depositPopularPlatforms">
                                <?php
                                $popular_platforms = array_slice($platforms, 0, 5);
                                foreach ($popular_platforms as $platform): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                    <?= htmlspecialchars($platform['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <div id="depositPlatformsList" style="max-height: 150px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 5px; display: none;"></div>
                            
                            <input type="hidden" id="depositPlatformId" value="">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Сумма пополнения *</label>
                            <div class="currency-input-group" style="margin-bottom: 10px;">
                                <input type="text" class="form-input" id="depositAmount" placeholder="0" inputmode="numeric">
                                <button type="button" class="currency-select-btn" id="selectCurrencyBtn">
                                    <span id="selectedCurrencyDisplay">RUB</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="depositPopularCurrencies">
                                <?php
                                $popular_currency_codes = ['RUB', 'USD', 'EUR', 'GBP', 'CNY'];
                                $popular_currencies = array_filter($fiat_currencies, function($c) use ($popular_currency_codes) {
                                    return in_array($c['code'], $popular_currency_codes);
                                });
                                foreach ($popular_currencies as $currency): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectCurrency('<?= $currency['code'] ?>', '<?= htmlspecialchars($currency['name']) ?>')">
                                    <?= $currency['code'] ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <div id="depositCurrenciesList" style="max-height: 150px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 5px; display: none;"></div>
                            
                            <input type="hidden" id="depositCurrency" value="RUB">
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Дата пополнения</label>
                            <input type="date" class="form-input" id="depositDate" required>
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-sticky-note"></i> Примечание</label>
                            <textarea class="form-input" id="depositNotes" rows="2" placeholder="Необязательный комментарий"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelDepositBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmDepositBtn" style="background: #00a86b;" onclick="confirmDeposit()">
                        <i class="fas fa-check-circle"></i> Пополнить
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно выбора валюты (актива) -->
        <div class="modal-overlay" id="currencySelectModal">
            <div class="modal" style="max-width: 400px;">
                <div class="modal-header">
                    <h2><i class="fas fa-coins" style="color: #1a5cff;"></i> Выберите валюту</h2>
                    <button class="modal-close" id="closeCurrencyModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <input type="text" class="form-input" id="currencySearch" placeholder="поиск или добавление валюты..." autocomplete="off" style="width: 100%;">
                    </div>
                    
                    <div>
                        <label><i class="fas fa-list" style="color: #1a5cff;"></i> Все валюты</label>
                        <div style="max-height: 250px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 8px;" id="allCurrenciesList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно выбора площадки -->
        <div class="modal-overlay" id="platformSelectModal">
            <div class="modal" style="max-width: 400px;">
                <div class="modal-header">
                    <h2><i class="fas fa-building" style="color: #1a5cff;"></i> Выберите площадку</h2>
                    <button class="modal-close" id="closePlatformModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <input type="text" class="form-input" id="platformSearch" placeholder="поиск или добавление площадки..." autocomplete="off" style="width: 100%;">
                    </div>
                    
                    <div>
                        <label><i class="fas fa-list" style="color: #1a5cff;"></i> Все площадки</label>
                        <div style="max-height: 250px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 8px;" id="allPlatformsList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления новой площадки -->
        <div class="modal-overlay" id="addPlatformModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #1a5cff;"></i> Добавление площадки</h2>
                    <button class="modal-close" id="closeAddPlatformModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addPlatformForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Название площадки *</label>
                            <input type="text" class="form-input" id="newPlatformName" placeholder="Например: Binance, Bybit, Т-Банк" value="" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Тип площадки *</label>
                            <div class="platform-type-buttons">
                                <button type="button" class="platform-type-btn" data-type="exchange">Биржа</button>
                                <button type="button" class="platform-type-btn" data-type="broker">Брокер</button>
                                <button type="button" class="platform-type-btn" data-type="bank">Банк</button>
                                <button type="button" class="platform-type-btn" data-type="wallet">Кошелек</button>
                                <button type="button" class="platform-type-btn" data-type="other">Другое</button>
                            </div>
                            <input type="hidden" id="newPlatformType" value="">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Выберите тип площадки
                            </small>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label><i class="fas fa-globe"></i> Страна</label>
                            <input type="text" class="form-input" id="newPlatformCountry" placeholder="Например: Россия, США, Китай">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddPlatformBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddPlatformBtn" style="background: #1a5cff;">
                        <i class="fas fa-save"></i> Сохранить площадку
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления новой валюты -->
        <div class="modal-overlay" id="addCurrencyModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #1a5cff;"></i> Добавление валюты</h2>
                    <button class="modal-close" id="closeAddCurrencyModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addCurrencyForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Код валюты *</label>
                            <input type="text" class="form-input" id="newCurrencyCode" placeholder="Например: RUB, USD, EUR, BTC" value="" readonly style="text-transform: uppercase;">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-font"></i> Название валюты *</label>
                            <input type="text" class="form-input" id="newCurrencyName" placeholder="Например: Российский рубль, Доллар США">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Тип валюты *</label>
                            <div class="currency-type-buttons">
                                <button type="button" class="currency-type-btn" data-type="fiat">Фиатная</button>
                                <button type="button" class="currency-type-btn" data-type="crypto">Криптовалюта</button>
                                <button type="button" class="currency-type-btn" data-type="stablecoin">Стейблкоин</button>
                                <button type="button" class="currency-type-btn" data-type="metal">Драгоценный металл</button>
                            </div>
                            <input type="hidden" id="newCurrencyType" value="">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Выберите тип валюты
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-symbol"></i> Символ валюты</label>
                            <input type="text" class="form-input" id="newCurrencySymbol" placeholder="Например: ₽, $, €, ₿" maxlength="5">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddCurrencyBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddCurrencyBtn" style="background: #1a5cff;">
                        <i class="fas fa-save"></i> Сохранить валюту
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно Покупка/Продажа -->
        <div class="modal-overlay" id="tradeModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2 id="tradeModalTitle"><i class="fas fa-arrow-down" style="color: #00a86b;"></i> <span id="tradeModalTitleText">Покупка</span></h2>
                    <button class="modal-close" id="closeTradeModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="tradeForm">
                        <input type="hidden" id="tradeOperationType" value="buy">

                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Площадка покупки *</label>
                            <button type="button" class="platform-select-btn" id="selectTradePlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedTradePlatformDisplay">Выбрать площадку</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="tradePopularPlatforms">
                                <?php
                                $popular_platforms = array_slice($platforms, 0, 5);
                                foreach ($popular_platforms as $platform): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectTradePlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                    <?= htmlspecialchars($platform['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="tradePlatformId" value="">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Актив и количество *</label>
                            <div class="currency-input-group" style="margin-bottom: 10px;">
                                <input type="text" class="form-input" id="tradeQuantity" placeholder="0" inputmode="numeric" style="text-align: right;">
                                <button type="button" class="currency-select-btn" id="selectTradeAssetBtn">
                                    <span id="selectedTradeAssetDisplay">Выбрать</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px;" id="tradePopularAssets">
                                <?php
                                $popular_assets = array_filter($assets_list, function($asset) {
                                    return in_array($asset['symbol'], ['BTC', 'ETH', 'USDT']);
                                });
                                foreach ($popular_assets as $asset): 
                                ?>
                                <button type="button" class="quick-asset-btn" onclick="selectTradeAsset('<?= $asset['id'] ?>', '<?= htmlspecialchars($asset['symbol']) ?>', '<?= $asset['type'] ?>')">
                                    <?= htmlspecialchars($asset['symbol']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="tradeAssetId" value="">
                            <input type="hidden" id="tradeAssetType" value="">
                        </div>

                        <div class="form-group" id="tradeFromPlatformGroup">
                            <label><i class="fas fa-arrow-right"></i> Площадка списания *</label>
                            <button type="button" class="platform-select-btn" id="selectTradeFromPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedTradeFromPlatformDisplay">Выбрать площадку</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="tradeFromPopularPlatforms">
                                <?php
                                $popular_platforms = array_slice($platforms, 0, 5);
                                foreach ($popular_platforms as $platform): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectTradeFromPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                    <?= htmlspecialchars($platform['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="tradeFromPlatformId" value="">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Выберите площадку, с которой будут списаны средства
                            </small>
                        </div>

                        <div id="tradeCryptoNetworkSection" style="display: none;">
                            <div class="form-group">
                                <label><i class="fas fa-network-wired"></i> Сеть (необязательно)</label>
                                <div class="currency-input-group">
                                    <button type="button" class="platform-select-btn" id="selectTradeNetworkBtn" style="width: 100%; justify-content: space-between;">
                                        <span id="selectedTradeNetworkDisplay">Выбрать сеть</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="tradeNetwork" value="">
                                
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px;" id="tradePopularNetworks">
                                    <!-- Популярные сети будут добавлены через JavaScript -->
                                </div>
                            </div>
                        </div>

                        <!-- Блок истории покупок для продажи -->
                        <div id="sellPurchaseHistory" style="display: none; margin-top: 15px;">
                            <div style="background: var(--bg-tertiary, #f8fafd); border-radius: 12px; padding: 12px; margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <span style="font-weight: 600; font-size: 13px;">
                                        <i class="fas fa-history"></i> История покупок
                                    </span>
                                    <span id="sellCurrentBalance" style="font-size: 12px; color: #00a86b;"></span>
                                </div>
                                
                                <div id="sellPurchaseList" style="max-height: 200px; overflow-y: auto;">
                                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                                    </div>
                                </div>
                                
                                <div id="sellQuickActions" style="margin-top: 12px; display: none;">
                                    <div style="border-top: 1px solid var(--border-color, #e0e6ed); margin: 10px 0;"></div>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <button type="button" id="sellQuickFillAllBtn" class="quick-platform-btn" style="background: #00a86b; color: white;">
                                            <i class="fas fa-arrow-up"></i> Продать всё
                                        </button>
                                        <button type="button" id="sellQuickFillAvgBtn" class="quick-platform-btn" style="background: #ff9f4a; color: white;">
                                            <i class="fas fa-chart-line"></i> По средней цене
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Цена за ед. *</label>
                            <div class="currency-input-group">
                                <input type="text" class="form-input" id="tradePrice" placeholder="0" inputmode="numeric" style="text-align: right;">
                                <button type="button" class="currency-select-btn" id="selectTradePriceCurrencyBtn">
                                    <span id="selectedTradePriceCurrencyDisplay">Выбрать</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="tradePopularPriceCurrencies">
                                <?php
                                $popular_currency_codes = ['USDT', 'RUB', 'USD'];
                                $popular_price_currencies = array_filter($all_currencies, function($c) use ($popular_currency_codes) {
                                    return in_array($c['code'], $popular_currency_codes);
                                });
                                foreach ($popular_price_currencies as $currency): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectTradePriceCurrency('<?= $currency['code'] ?>')">
                                    <?= $currency['code'] ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="tradePriceCurrency" value="">
                        </div>

                        <div class="form-row" style="margin-top: 10px;">
                            <div class="form-group">
                                <label><i class="fas fa-percent"></i> Комиссия</label>
                                <div class="currency-input-group">
                                    <input type="text" class="form-input" id="tradeCommission" placeholder="0" inputmode="numeric" style="text-align: right;">
                                    <button type="button" class="currency-select-btn" id="selectTradeCommissionCurrencyBtn">
                                        <span id="selectedTradeCommissionCurrencyDisplay">Выбрать</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                                
                                <input type="hidden" id="tradeCommissionCurrency" value="">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-calculator"></i> Итого</label>
                                <input type="text" class="form-input" id="tradeTotal" value="0" style="width: 100%; background: var(--bg-tertiary); text-align: center; font-weight: 600; font-size: 18px;" readonly>
                                <small style="color: #6b7a8f; display: block; margin-top: 5px; text-align: center;">
                                    <i class="fas fa-info-circle"></i> <span id="tradeTotalQuantity"></span>
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Дата операции</label>
                            <input type="date" class="form-input" id="tradeDate" required>
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-sticky-note"></i> Комментарий</label>
                            <textarea class="form-input" id="tradeNotes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelTradeBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmTradeBtn" onclick="confirmTrade()">
                        <i class="fas fa-check-circle"></i> <span id="confirmTradeBtnText">Купить</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно Перевода -->
        <div class="modal-overlay" id="transferModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-exchange-alt" style="color: #ff9f4a;"></i> Перевод</h2>
                    <button class="modal-close" id="closeTransferModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="transferForm">
                        <!-- Блок выбора площадок Откуда и Куда в одной строке -->
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label>Откуда *</label>
                                <button type="button" class="platform-select-btn" id="selectFromPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                    <span id="selectedFromPlatformDisplay">Выбрать площадку</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="transferFromPopularPlatforms">
                                    <?php
                                    $popular_platforms = array_slice($platforms, 0, 5);
                                    foreach ($popular_platforms as $platform): 
                                    ?>
                                    <button type="button" class="quick-platform-btn" onclick="selectFromPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                        <?= htmlspecialchars($platform['name']) ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div id="transferFromPlatformsList" style="max-height: 150px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 5px; display: none;"></div>
                                
                                <input type="hidden" id="transferFromPlatformId" value="">
                            </div>

                            <div class="form-group">
                                <label>Куда *</label>
                                <button type="button" class="platform-select-btn" id="selectToPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                    <span id="selectedToPlatformDisplay">Выбрать площадку</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="transferToPopularPlatforms">
                                    <?php
                                    $popular_platforms = array_slice($platforms, 0, 5);
                                    foreach ($popular_platforms as $platform): 
                                    ?>
                                    <button type="button" class="quick-platform-btn" onclick="selectToPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                        <?= htmlspecialchars($platform['name']) ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div id="transferToPlatformsList" style="max-height: 150px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 5px; display: none;"></div>
                                
                                <input type="hidden" id="transferToPlatformId" value="">
                            </div>
                        </div>

                        <!-- Блок баланса площадки отправителя -->
                        <div id="transferFromPlatformBalance" style="display: none; margin-top: 10px; margin-bottom: 15px;">
                            <div style="background: var(--bg-tertiary, #f8fafd); border-radius: 12px; padding: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <span id="platformBalanceTitle" style="font-weight: 600; font-size: 13px;">
                                        <i class="fas fa-wallet"></i> Баланс площадки
                                    </span>
                                    <span id="transferPlatformTotalValue" style="font-size: 12px; font-weight: 500; color: #ff9f4a;"></span>
                                </div>
                                
                                <div id="transferPlatformAssetsList" style="max-height: 200px; overflow-y: auto;">
                                    <div style="text-align: center; padding: 15px; color: #6b7a8f;">
                                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                                    </div>
                                </div>
                                
                                <div id="transferPlatformTotal" style="margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--border-color, #e0e6ed); display: none;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                                        <span>Всего:</span>
                                        <span id="transferPlatformTotalUsd" style="font-weight: 600;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Что переводим *</label>
                            <div class="currency-input-group" style="margin-bottom: 10px;">
                                <input type="text" class="form-input" id="transferAmount" placeholder="0" inputmode="numeric" style="text-align: right;">
                                <button type="button" class="currency-select-btn" id="selectAssetBtn">
                                    <span id="selectedAssetDisplay">Выбрать</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="transferPopularAssets">
                                <?php
                                $popular_assets = array_filter($assets_list, function($asset) {
                                    return in_array($asset['symbol'], ['BTC', 'ETH', 'USDT']);
                                });
                                foreach ($popular_assets as $asset): 
                                ?>
                                <button type="button" class="quick-asset-btn" onclick="selectAsset('<?= $asset['id'] ?>', '<?= htmlspecialchars($asset['symbol']) ?>')">
                                    <?= htmlspecialchars($asset['symbol']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="transferAssetId" value="">
                        </div>

                        <div id="transferCryptoNetworkSection" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-network-wired"></i> Сеть отправителя *</label>
                                    <button type="button" class="platform-select-btn" id="selectFromNetworkBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                        <span id="selectedFromNetworkDisplay">Выбрать сеть</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <input type="hidden" id="transferNetworkFrom" value="">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-network-wired"></i> Сеть получателя *</label>
                                    <button type="button" class="platform-select-btn" id="selectToNetworkBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                        <span id="selectedToNetworkDisplay">Выбрать сеть</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <input type="hidden" id="transferNetworkTo" value="">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-percent"></i> Комиссия</label>
                            <div class="currency-input-group" style="margin-bottom: 10px;">
                                <input type="text" class="form-input" id="transferCommission" placeholder="0" inputmode="numeric" style="text-align: right;">
                                <button type="button" class="currency-select-btn" id="selectCommissionCurrencyBtn">
                                    <span id="selectedCommissionCurrencyDisplay">Выбрать</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <input type="hidden" id="transferCommissionCurrency" value="">
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Дата перевода</label>
                            <input type="date" class="form-input" id="transferDate">
                        </div>

                        <div class="form-group">
                            <label><i class="far fa-sticky-note"></i> Комментарий</label>
                            <textarea class="form-input" id="transferNotes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelTransferBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmTransferBtn" style="background: #ff9f4a;" onclick="confirmTransfer()">
                        <i class="fas fa-exchange-alt"></i> Перевести
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно создания лимитного ордера -->
        <div class="modal-overlay" id="limitOrderModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-clock" style="color: #ff9f4a;"></i> Лимитный ордер</h2>
                    <button class="modal-close" id="closeLimitOrderModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="limitOrderForm">
                        <input type="hidden" id="limitOrderOperationType" value="buy">
                        
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Тип операции</label>
                            <div style="display: flex; gap: 10px; margin-top: 5px;">
                                <button type="button" class="platform-type-btn limit-type-btn" data-type="buy" style="flex: 1; background: #00a86b; color: white; border: none;">Покупка</button>
                                <button type="button" class="platform-type-btn limit-type-btn" data-type="sell" style="flex: 1; background: #e53e3e; color: white; border: none;">Продажа</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Площадка *</label>
                            <button type="button" class="platform-select-btn" id="selectLimitPlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedLimitPlatformDisplay">Выбрать площадку</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="limitPopularPlatforms">
                                <?php
                                $popular_platforms = array_slice($platforms, 0, 5);
                                foreach ($popular_platforms as $platform): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectLimitPlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                    <?= htmlspecialchars($platform['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="limitPlatformId" value="">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Актив *</label>
                            <button type="button" class="platform-select-btn" id="selectLimitAssetBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedLimitAssetDisplay">Выбрать актив</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="limitPopularAssets">
                                <?php
                                $popular_assets = array_filter($assets_list, function($asset) {
                                    return in_array($asset['symbol'], ['BTC', 'ETH', 'USDT', 'SBER', 'GAZP']);
                                });
                                foreach ($popular_assets as $asset): 
                                ?>
                                <button type="button" class="quick-asset-btn" onclick="selectLimitAsset('<?= $asset['id'] ?>', '<?= htmlspecialchars($asset['symbol']) ?>')">
                                    <?= htmlspecialchars($asset['symbol']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="limitAssetId" value="">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-balance-scale"></i> Количество *</label>
                                <input type="text" class="form-input" id="limitQuantity" placeholder="0" inputmode="numeric">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Лимитная цена *</label>
                                <div class="currency-input-group">
                                    <input type="text" class="form-input" id="limitPrice" placeholder="0" inputmode="numeric" style="text-align: right;">
                                    <button type="button" class="currency-select-btn" id="selectLimitCurrencyBtn">
                                        <span id="selectedLimitCurrencyDisplay">Выбрать</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="limitCurrency" value="">
                                
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="limitPopularCurrencies">
                                    <button type="button" class="quick-platform-btn" onclick="selectLimitCurrency('USDT')">USDT</button>
                                    <button type="button" class="quick-platform-btn" onclick="selectLimitCurrency('USD')">USD</button>
                                    <button type="button" class="quick-platform-btn" onclick="selectLimitCurrency('RUB')">RUB</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Действителен до (необязательно)</label>
                            <input type="date" class="form-input" id="limitExpiryDate">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="far fa-sticky-note"></i> Комментарий</label>
                            <textarea class="form-input" id="limitNotes" rows="2"></textarea>
                        </div>
                        
                        <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 15px; margin-top: 10px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: #6b7a8f;">Ориентировочная сумма:</span>
                                <span style="font-weight: 600;" id="limitTotalEstimate">0 USD</span>
                            </div>
                            <div style="font-size: 12px; color: #6b7a8f;">
                                <i class="fas fa-info-circle"></i> Сумма будет заблокирована при размещении ордера
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelLimitOrderBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmLimitOrderBtn" style="background: #ff9f4a;">
                        <i class="fas fa-clock"></i> Разместить ордер
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно подтверждения исполнения ордера -->
        <div class="modal-overlay" id="executeOrderModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color, #e0e6ed);">
                    <h2><i class="fas fa-check-circle" style="color: #00a86b;"></i> Подтверждение исполнения</h2>
                    <button class="modal-close" id="closeExecuteModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="background: var(--bg-secondary, #f8fafd); padding: 20px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                            <div style="width: 48px; height: 48px; background: rgba(0, 168, 107, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chart-line" style="color: #00a86b; font-size: 24px;"></i>
                            </div>
                            <div>
                                <div style="font-size: 20px; font-weight: 600; color: var(--text-primary, #2c3e50);" id="executeOrderTitle">Покупка BTC</div>
                                <div style="font-size: 14px; color: var(--text-secondary, #6b7a8f);" id="executeOrderPlatform">Bybit</div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                            <div style="background: var(--bg-primary, white); padding: 12px; border-radius: 10px;">
                                <div style="font-size: 12px; color: var(--text-secondary, #6b7a8f); margin-bottom: 4px;">Количество</div>
                                <div style="font-size: 18px; font-weight: 600; color: var(--text-primary, #2c3e50);" id="executeOrderQuantity">1.0000 BTC</div>
                            </div>
                            <div style="background: var(--bg-primary, white); padding: 12px; border-radius: 10px;">
                                <div style="font-size: 12px; color: var(--text-secondary, #6b7a8f); margin-bottom: 4px;">Лимитная цена</div>
                                <div style="font-size: 18px; font-weight: 600; color: var(--text-primary, #2c3e50);" id="executeOrderPrice">$10,000.00</div>
                            </div>
                        </div>
                        
                        <div style="background: var(--bg-primary, white); border-radius: 10px; padding: 16px; border: 1px solid var(--border-color, #e0e6ed);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color, #e0e6ed);">
                                <span style="color: var(--text-secondary, #6b7a8f);">Общая сумма:</span>
                                <span style="font-weight: 700; font-size: 20px; color: #00a86b;" id="executeOrderTotal">$10,000.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="color: var(--text-secondary, #6b7a8f);">Создан:</span>
                                <span style="color: var(--text-primary, #2c3e50); font-weight: 500;" id="executeOrderCreated">19.03.2026 16:05</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary, #6b7a8f);">Действителен до:</span>
                                <span style="color: var(--text-primary, #2c3e50); font-weight: 500;" id="executeOrderExpiry">Бессрочно</span>
                            </div>
                        </div>
                        
                        <div style="margin-top: 16px; padding: 12px; background: rgba(255, 159, 74, 0.1); border-radius: 8px; border: 1px solid rgba(255, 159, 74, 0.3);">
                            <div style="display: flex; gap: 8px; align-items: flex-start;">
                                <i class="fas fa-info-circle" style="color: #ff9f4a; margin-top: 2px;"></i>
                                <div>
                                    <div style="font-weight: 600; margin-bottom: 4px; color: var(--text-primary, #2c3e50);">Подтвердите действие</div>
                                    <div style="font-size: 13px; color: var(--text-secondary, #6b7a8f);" id="executeOrderWarning">Будет создана сделка на покупку. Средства будут списаны автоматически.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button class="btn btn-secondary" id="cancelExecuteBtn">Отмена</button>
                        <button class="btn btn-primary" id="confirmExecuteBtn" style="background: #00a86b;">
                            <i class="fas fa-check-circle"></i> Подтвердить исполнение
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно подтверждения отмены ордера -->
        <div class="modal-overlay" id="cancelOrderModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color, #e0e6ed);">
                    <h2><i class="fas fa-times-circle" style="color: #e53e3e;"></i> Подтверждение отмены</h2>
                    <button class="modal-close" id="closeCancelModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="background: var(--bg-secondary, #f8fafd); padding: 24px; margin-bottom: 20px; text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="color: #e53e3e; font-size: 48px; margin-bottom: 16px;"></i>
                        <div style="font-size: 20px; font-weight: 600; margin-bottom: 8px; color: var(--text-primary, #2c3e50);" id="cancelOrderTitle">Отмена ордера</div>
                        <div style="color: var(--text-secondary, #6b7a8f); margin-bottom: 20px; font-size: 14px;" id="cancelOrderDescription">Вы уверены, что хотите отменить ордер?</div>
                        
                        <div style="background: var(--bg-primary, white); border-radius: 10px; padding: 16px; text-align: left; border: 1px solid var(--border-color, #e0e6ed);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color, #e0e6ed);">
                                <span style="color: var(--text-secondary, #6b7a8f);">Площадка:</span>
                                <span style="font-weight: 600; color: var(--text-primary, #2c3e50);" id="cancelOrderPlatform">Bybit</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span style="color: var(--text-secondary, #6b7a8f);">Цена:</span>
                                <span style="font-weight: 600; color: var(--text-primary, #2c3e50);" id="cancelOrderPrice">$10,000.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: var(--text-secondary, #6b7a8f);">Количество:</span>
                                <span style="font-weight: 600; color: var(--text-primary, #2c3e50);" id="cancelOrderQuantity">1.0000 BTC</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button class="btn btn-secondary" id="cancelCancelBtn">Нет, оставить</button>
                        <button class="btn btn-primary" id="confirmCancelBtn" style="background: #e53e3e;">
                            <i class="fas fa-times-circle"></i> Да, отменить
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления/редактирования заметки -->
        <div class="modal-overlay" id="noteModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2 id="noteModalTitle"><i class="fas fa-sticky-note" style="color: #ff9f4a;"></i> <span id="noteModalTitleText">Добавить заметку</span></h2>
                    <button class="modal-close" id="closeNoteModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="noteForm">
                        <input type="hidden" id="noteId" value="">
                        
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Заголовок (необязательно)</label>
                            <input type="text" class="form-input" id="noteTitle" placeholder="Краткий заголовок заметки">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Содержание *</label>
                            <textarea class="form-input" id="noteContent" rows="4" placeholder="Введите текст заметки..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Тип заметки</label>
                            <div class="note-type-buttons" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <button type="button" class="platform-type-btn note-type-option" data-type="general" style="flex: 1;">📝 Обычная</button>
                                <button type="button" class="platform-type-btn note-type-option" data-type="reminder" style="flex: 1;">📌 Напоминание</button>
                                <button type="button" class="platform-type-btn note-type-option" data-type="idea" style="flex: 1;">💡 Идея</button>
                                <button type="button" class="platform-type-btn note-type-option" data-type="important" style="flex: 1;">⚠️ Важное</button>
                            </div>
                            <input type="hidden" id="noteType" value="general">
                        </div>
                        
                        <div class="form-group" id="reminderDateGroup" style="display: none;">
                            <label><i class="far fa-calendar-alt"></i> Дата напоминания</label>
                            <input type="date" class="form-input" id="noteReminderDate">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelNoteBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmNoteBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> <span id="confirmNoteBtnText">Сохранить</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно просмотра архивных заметок -->
        <div class="modal-overlay" id="archivedNotesModal">
            <div class="modal" style="max-width: 600px; max-height: 80vh;">
                <div class="modal-header">
                    <h2><i class="fas fa-archive" style="color: #6b7a8f;"></i> Архивные заметки</h2>
                    <button class="modal-close" id="closeArchivedModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="archivedNotesList" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeArchivedModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно подтверждения удаления -->
        <div class="modal-overlay" id="confirmDeleteModal">
            <div class="modal" style="max-width: 400px;">
                <div class="modal-header">
                    <h2><i class="fas fa-trash-alt" style="color: #e53e3e;"></i> Подтверждение удаления</h2>
                    <button class="modal-close" id="closeConfirmDeleteBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="text-align: center; padding: 20px;">
                        Вы уверены, что хотите безвозвратно удалить эту заметку?
                    </p>
                    <div id="deleteNoteInfo" style="background: var(--bg-tertiary); padding: 12px; border-radius: 8px; margin-top: 10px;">
                        <!-- Информация о заметке -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelDeleteBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmDeleteBtn" style="background: #e53e3e;">
                        <i class="fas fa-trash-alt"></i> Удалить
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно выбора сети -->
        <div class="modal-overlay" id="networkSelectModal">
            <div class="modal" style="max-width: 400px;">
                <div class="modal-header">
                    <h2 id="networkModalTitle"><i class="fas fa-network-wired" style="color: #ff9f4a;"></i> Выберите сеть</h2>
                    <button class="modal-close" id="closeNetworkModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <input type="text" class="form-input" id="networkSearch" placeholder="поиск или добавление сети..." autocomplete="off" style="width: 100%;">
                    </div>
                    
                    <div>
                        <label><i class="fas fa-list"></i> Все сети</label>
                        <div style="max-height: 300px; overflow-y: auto; margin-top: 8px; border: 1px solid #edf2f7; border-radius: 12px; padding: 8px;" id="allNetworksList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления новой сети -->
        <div class="modal-overlay" id="addNetworkModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #ff9f4a;"></i> Добавление сети</h2>
                    <button class="modal-close" id="closeAddNetworkModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addNetworkForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Аббревиатура сети *</label>
                            <input type="text" class="form-input" id="newNetworkName" placeholder="Например: ERC20, BEP20, TRC20, SOL" value="" readonly style="text-transform: uppercase;">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Краткое название (будет отображаться в списке)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-font"></i> Полное название сети</label>
                            <input type="text" class="form-input" id="newNetworkFullName" placeholder="Например: Ethereum (ERC-20), Binance Smart Chain (BEP-20)">
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Полное название (будет отображаться под аббревиатурой)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-palette"></i> Цвет сети (необязательно)</label>
                            <input type="color" class="form-input" id="newNetworkColor" value="#ff9f4a" style="height: 48px; padding: 6px;">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-icons"></i> Иконка (автоматически)</label>
                            <div style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; background: var(--bg-tertiary); border-radius: 12px;">
                                <div id="previewNetworkIcon" style="width: 32px; height: 32px; background: #ff9f4a20; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #ff9f4a;">
                                    <i class="fas fa-network-wired"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 500;" id="previewNetworkName">ERC20</div>
                                    <div style="font-size: 11px; color: #6b7a8f;" id="previewNetworkFullName">Ethereum (ERC-20)</div>
                                </div>
                            </div>
                            <small style="color: #6b7a8f; display: block; margin-top: 5px;">
                                <i class="fas fa-info-circle"></i> Иконка определяется автоматически по названию сети
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddNetworkBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddNetworkBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> Сохранить сеть
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно активов площадки -->
        <div class="modal-overlay" id="platformAssetsModal">
            <div class="modal" style="max-width: 600px; max-height: 80vh;">
                <div class="modal-header">
                    <h2 id="platformAssetsModalTitle">
                        <i class="fas fa-building" style="color: #1a5cff;"></i> 
                        <span id="platformAssetsName">Активы площадки</span>
                    </h2>
                    <button class="modal-close" id="closePlatformAssetsModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="platformAssetsBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closePlatformAssetsModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно активов сети -->
        <div class="modal-overlay" id="networkAssetsModal">
            <div class="modal" style="max-width: 650px; max-height: 80vh;">
                <div class="modal-header">
                    <h2 id="networkAssetsModalTitle">
                        <i class="fas fa-network-wired" style="color: #ff9f4a;"></i> 
                        <span id="networkAssetsName">Активы сети</span>
                    </h2>
                    <button class="modal-close" id="closeNetworkAssetsModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="networkAssetsBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeNetworkAssetsModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно активов сектора -->
        <div class="modal-overlay" id="sectorAssetsModal">
            <div class="modal" style="max-width: 650px; max-height: 80vh;">
                <div class="modal-header">
                    <h2 id="sectorAssetsModalTitle">
                        <i class="fas fa-chart-pie" style="color: #4a9eff;"></i> 
                        <span id="sectorAssetsName">Активы сектора</span>
                    </h2>
                    <button class="modal-close" id="closeSectorAssetsModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="sectorAssetsBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeSectorAssetsModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно активов по типам криптовалют -->
        <div class="modal-overlay" id="cryptoTypeModal">
            <div class="modal" style="max-width: 650px; max-height: 80vh;">
                <div class="modal-header">
                    <h2 id="cryptoTypeModalTitle">
                        <i class="fas fa-coins" style="color: #ff9f4a;"></i> 
                        <span id="cryptoTypeName">Активы</span>
                    </h2>
                    <button class="modal-close" id="closeCryptoTypeModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="cryptoTypeBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeCryptoTypeModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно расходов -->
        <div class="modal-overlay" id="expenseModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-receipt" style="color: #ff9f4a;"></i> Добавить расход</h2>
                    <button class="modal-close" id="closeExpenseModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="expenseForm">
                        <!-- Блок выбора площадки -->
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Площадка списания *</label>
                            <button type="button" class="platform-select-btn" id="selectExpensePlatformBtn" style="width: 100%; justify-content: space-between; margin-bottom: 10px;">
                                <span id="selectedExpensePlatformDisplay">Выбрать площадку</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="expensePopularPlatforms">
                                <?php
                                $popular_platforms = array_slice($platforms, 0, 5);
                                foreach ($popular_platforms as $platform): 
                                ?>
                                <button type="button" class="quick-platform-btn" onclick="selectExpensePlatform('<?= $platform['id'] ?>', '<?= htmlspecialchars($platform['name']) ?>')">
                                    <?= htmlspecialchars($platform['name']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="expensePlatformId" value="">
                        </div>

                        <!-- Блок баланса площадки (расходы) -->
                        <div id="expensePlatformBalance" style="display: none; margin-top: 10px; margin-bottom: 15px;">
                            <div style="background: var(--bg-tertiary, #f8fafd); border-radius: 12px; padding: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <span id="expenseBalanceTitle" style="font-weight: 600; font-size: 13px;">
                                        <i class="fas fa-wallet"></i> Баланс площадки
                                    </span>
                                    <span id="expensePlatformTotalValue" style="font-size: 12px; font-weight: 500; color: #ff9f4a;"></span>
                                </div>
                                
                                <div id="expensePlatformAssetsList" style="max-height: 200px; overflow-y: auto;">
                                    <div style="text-align: center; padding: 15px; color: #6b7a8f;">
                                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                                    </div>
                                </div>
                                
                                <div id="expensePlatformTotal" style="margin-top: 10px; padding-top: 8px; border-top: 1px solid var(--border-color, #e0e6ed); display: none;">
                                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                                        <span>Всего:</span>
                                        <span id="expensePlatformTotalUsd" style="font-weight: 600;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Блок выбора актива и суммы -->
                        <div class="form-group">
                            <label><i class="fas fa-coins"></i> Актив и сумма расхода *</label>
                            <div class="currency-input-group" style="margin-bottom: 10px;">
                                <input type="text" class="form-input" id="expenseAmount" placeholder="0" inputmode="numeric" style="text-align: right;">
                                <button type="button" class="currency-select-btn" id="selectExpenseAssetBtn">
                                    <span id="selectedExpenseAssetDisplay">Выбрать</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px;" id="expensePopularAssets">
                                <?php
                                $popular_assets = array_filter($assets_list, function($asset) {
                                    return in_array($asset['symbol'], ['RUB', 'USD', 'USDT']);
                                });
                                foreach ($popular_assets as $asset): 
                                ?>
                                <button type="button" class="quick-asset-btn" onclick="selectExpenseAsset('<?= $asset['id'] ?>', '<?= htmlspecialchars($asset['symbol']) ?>')">
                                    <?= htmlspecialchars($asset['symbol']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" id="expenseAssetId" value="">
                        </div>

                        <!-- Описание расхода -->
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> Описание</label>
                            <textarea class="form-input" id="expenseDescription" rows="2" placeholder="..."></textarea>
                        </div>

                        <!-- Категория расхода -->
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Категория *</label>
                            <div id="expenseCategoriesList" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px;">
                                <div style="text-align: center; padding: 10px; width: 100%; color: #6b7a8f;">
                                    <i class="fas fa-spinner fa-spin"></i> Загрузка категорий...
                                </div>
                            </div>
                            <div style="margin-top: 8px;">
                                <button type="button" class="quick-platform-btn" id="addExpenseCategoryBtn" style="width: 100%;">
                                    <i class="fas fa-plus-circle"></i> Добавить новую категорию
                                </button>
                            </div>
                            <input type="hidden" id="expenseCategoryId" value="">
                        </div>

                        <!-- Дата расхода -->
                        <div class="form-group">
                            <label><i class="far fa-calendar-alt"></i> Дата расхода</label>
                            <input type="date" class="form-input" id="expenseDate" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelExpenseBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmExpenseBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> Сохранить расход
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно просмотра расходов -->
        <div class="modal-overlay" id="expensesListModal">
            <div class="modal" style="max-width: 700px; max-height: 80vh;">
                <div class="modal-header">
                    <h2><i class="fas fa-chart-line" style="color: #ff9f4a;"></i> Мои расходы</h2>
                    <button class="modal-close" id="closeExpensesListModalBtn">&times;</button>
                </div>
                <div class="modal-body" id="expensesListBody" style="max-height: 60vh; overflow-y: auto;">
                    <div style="text-align: center; padding: 30px;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeExpensesListModalFooterBtn">Закрыть</button>
                    <button class="btn btn-primary" id="addNewExpenseBtn" style="background: #ff9f4a;">
                        <i class="fas fa-plus-circle"></i> Добавить расход
                    </button>
                </div>
            </div>
        </div>

        <!-- Модальное окно добавления категории расходов -->
        <div class="modal-overlay" id="addExpenseCategoryModal">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2><i class="fas fa-plus-circle" style="color: #ff9f4a;"></i> Добавить категорию расходов</h2>
                    <button class="modal-close" id="closeAddCategoryModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addExpenseCategoryForm">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Название (англ) *</label>
                            <input type="text" class="form-input" id="newCategoryName" placeholder="Например: food, transport, shopping" required>
                            <small style="color: #6b7a8f;">Уникальный идентификатор категории</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-font"></i> Название (рус) *</label>
                            <input type="text" class="form-input" id="newCategoryNameRu" placeholder="Например: Продукты, Транспорт, Покупки" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-icons"></i> Иконка</label>
                            <div class="currency-input-group">
                                <input type="text" class="form-input" id="newCategoryIcon" placeholder="fas fa-tag" value="fas fa-tag">
                                <button type="button" class="currency-select-btn" id="selectIconBtn" style="width: 80px;">
                                    <i class="fas fa-search"></i> Выбрать
                                </button>
                            </div>
                            <div id="iconPreview" style="margin-top: 8px; padding: 8px; background: var(--bg-tertiary); border-radius: 8px; text-align: center;">
                                <i class="fas fa-tag" style="font-size: 24px; color: #ff9f4a;"></i>
                                <span id="iconPreviewText" style="margin-left: 8px;">fas fa-tag</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-palette"></i> Цвет</label>
                            <input type="color" class="form-input" id="newCategoryColor" value="#ff9f4a" style="height: 48px; padding: 6px;">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelAddCategoryBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmAddCategoryBtn" style="background: #ff9f4a;">
                        <i class="fas fa-save"></i> Сохранить категорию
                    </button>
                </div>
            </div>
        </div>

        <!-- Простое модальное окно выбора иконки (можно расширить) -->
        <div class="modal-overlay" id="iconSelectModal">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h2><i class="fas fa-icons"></i> Выберите иконку</h2>
                    <button class="modal-close" id="closeIconModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <input type="text" class="form-input" id="iconSearch" placeholder="Поиск иконки...">
                    </div>
                    <div id="iconsList" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; max-height: 300px; overflow-y: auto;">
                        <!-- Популярные иконки -->
                        <div class="icon-option" data-icon="fas fa-utensils" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-utensils" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">food</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-car" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-car" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">transport</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-film" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-film" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">entertainment</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-shopping-bag" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-shopping-bag" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">shopping</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-heartbeat" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-heartbeat" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">health</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-graduation-cap" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-graduation-cap" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">education</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-home" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-home" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">utilities</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-coffee" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-coffee" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">coffee</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-plane" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-plane" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">travel</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-gift" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-gift" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">gift</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-wifi" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-wifi" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">internet</div>
                        </div>
                        <div class="icon-option" data-icon="fas fa-mobile-alt" style="padding: 10px; text-align: center; cursor: pointer; border-radius: 8px;">
                            <i class="fas fa-mobile-alt" style="font-size: 24px;"></i>
                            <div style="font-size: 10px;">phone</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeIconModalFooterBtn">Закрыть</button>
                </div>
            </div>
        </div>

        <!-- Модальное окно продажи -->
        <div class="modal-overlay" id="sellModal">
            <div class="modal" style="max-width: 650px;">
                <div class="modal-header">
                    <h2><i class="fas fa-arrow-up" style="color: #e53e3e;"></i> Продажа актива</h2>
                    <button class="modal-close" id="closeSellModalBtn">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="sellForm">
                        <input type="hidden" id="sellPlatformId" value="">
                        
                        <!-- ШАГ 1: Выбор актива и цены -->
                        <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                            <h4 style="margin-bottom: 15px;"><i class="fas fa-chart-line"></i> Параметры продажи</h4>
                            
                            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label><i class="fas fa-coins"></i> Актив *</label>
                                    <button type="button" class="platform-select-btn" id="selectSellAssetBtn" style="width: 100%; justify-content: space-between;">
                                        <span id="selectedSellAssetDisplay">Выбрать актив</span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <input type="hidden" id="sellAssetId" value="">
                                    <input type="hidden" id="sellAssetType" value="">
                                    
                                    <!-- ДОБАВИТЬ БЫСТРЫЕ КНОПКИ АКТИВОВ -->
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;" id="sellPopularAssets">
                                        <button type="button" class="quick-asset-btn" onclick="selectSellAssetFromQuick('BTC')">BTC</button>
                                        <button type="button" class="quick-asset-btn" onclick="selectSellAssetFromQuick('ETH')">ETH</button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> Цена за единицу *</label>
                                    <div class="currency-input-group">
                                        <input type="text" class="form-input" id="sellPrice" placeholder="0">
                                        <button type="button" class="currency-select-btn" id="selectSellPriceCurrencyBtn">
                                            <span id="selectedSellPriceCurrencyDisplay">Выбрать</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" id="sellPriceCurrency" value="">
                                    
                                    <!-- ДОБАВИТЬ БЫСТРЫЕ КНОПКИ ВАЛЮТ ЦЕНЫ -->
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;" id="sellPopularPriceCurrencies">
                                        <button type="button" class="quick-platform-btn" onclick="selectSellPriceCurrency('USDT')">USDT</button>
                                        <button type="button" class="quick-platform-btn" onclick="selectSellPriceCurrency('RUB')">RUB</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- КОНТЕЙНЕР ДЛЯ ДАННЫХ (заполняется через loadSellData) -->
                        <div id="sellLotsContainer" style="display: none;">
                            <div id="sellLotsList"></div>
                        </div>

                        <!-- Детали сделки -->
                        <div id="sellTransactionDetails" style="display: none; margin-top: 15px;">
                            <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 16px;">
                                <h4 style="margin-bottom: 12px;"><i class="fas fa-receipt"></i> Детали сделки</h4>
                                
                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <div class="form-group">
                                        <label>Комиссия</label>
                                        <div class="currency-input-group">
                                            <input type="text" class="form-input" id="sellCommission" placeholder="0">
                                            <button type="button" class="currency-select-btn" id="selectSellCommissionCurrencyBtn">
                                                <span id="selectedSellCommissionCurrencyDisplay">Выбрать</span>
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" id="sellCommissionCurrency" value="">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Дата операции</label>
                                        <input type="date" class="form-input" id="sellDate">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Комментарий</label>
                                    <textarea class="form-input" id="sellNotes" rows="2"></textarea>
                                </div>
                                
                                <div style="background: var(--bg-secondary); border-radius: 12px; padding: 12px; margin-top: 12px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                        <span>Продается:</span>
                                        <span id="sellFinalQuantity" style="font-weight: 600;">0</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                        <span>Средняя цена покупки:</span>
                                        <span id="sellFinalAvgPrice" style="font-weight: 600;">0</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                        <span>Цена продажи:</span>
                                        <span id="sellFinalPrice" style="font-weight: 600;">0</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                        <span>Сумма продажи:</span>
                                        <span id="sellFinalTotal" style="font-weight: 600; color: #00a86b;">0</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                        <span>Площадка списания:</span>
                                        <span id="sellFinalPlatform" style="font-weight: 600;">Не выбрана</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding-top: 6px; border-top: 1px solid var(--border-color);">
                                        <span>Прибыль / Убыток:</span>
                                        <span id="sellFinalProfit" style="font-weight: 700; font-size: 16px;">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelSellBtn">Отмена</button>
                    <button class="btn btn-primary" id="confirmSellBtn" style="background: #e53e3e;">
                        <i class="fas fa-check-circle"></i> Продать
                    </button>
                </div>
            </div>
        </div>

        <!-- Шапка сайта с логотипом и кнопками -->
        <div class="site-header">
            <div class="logo-container">
                <div class="logo-icon" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="logo-text">
                    <span class="logo-title">Планеро.Инвестиции</span>
                    <span class="logo-subtitle">Анализ инвестиций</span>
                </div>
            </div>
            
            <!-- Кнопки операций -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="currency-btn operation-type-btn" data-type="buy">
                    <i class="fas fa-arrow-down"></i> Покупка
                </button>
                <button type="button" class="currency-btn operation-type-btn" data-type="sell">
                    <i class="fas fa-arrow-up"></i> Продажа
                </button>
                <button type="button" class="currency-btn operation-type-btn" data-type="transfer">
                    <i class="fas fa-exchange-alt"></i> Перевод
                </button>
                <button type="button" class="currency-btn operation-type-btn" data-type="deposit">
                    <i class="fas fa-plus-circle"></i> Пополнить
                </button>
                <button type="button" class="operation-type-btn" data-type="expense">
                    <i class="fas fa-receipt"></i> Расходы
                </button>
                <button id="themeToggleBtn" class="theme-toggle-btn">
                    <i class="fas <?= $current_theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
                    <span id="themeToggleText"><?= $current_theme === 'dark' ? 'Светлая' : 'Темная' ?></span>
                </button>
            </div>
        </div>

        <!-- HEADER на всю ширину -->
        <div class="header">
            <div class="portfolio-value">                
                <div style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap; align-items: center;">
                    <div style="padding: 10px 16px; border-radius: 12px;">
                        <span class="value-label">Текущая стоимость портфеля</span>
                        <div style="display: flex; align-items: baseline; gap: 20px; flex-wrap: wrap;">
                            <div>
                                <span class="value-amount" id="usdValue"><?= number_format($total_usd, 2, '.', ' ') ?> $</span>
                                <br />
                                <span class="value-amount" id="rubValue"><?= number_format($total_rub, 0, '.', ' ') ?> ₽</span>
                            </div>  
                        </div>
                    </div>

                    <!-- Блок доходности - проценты под текстом -->
                    <div style="background: <?= $profit_usd >= 0 ? '#e8f5e9' : '#ffe6e6' ?>; padding: 10px 16px; border-radius: 12px; min-width: 200px;">
                        <div style="font-size: 12px; color: <?= $profit_usd >= 0 ? '#2e7d32' : '#c62828' ?>; font-weight: 500; display: flex; align-items: center; gap: 4px;">
                            <i class="fas <?= $profit_icon ?>" style="font-size: 10px;"></i>
                            ДОХОДНОСТЬ 
                        </div>
                        
                        <div style="font-weight: 600; font-size: 18px; color: <?= $profit_usd >= 0 ? '#2e7d32' : '#c62828' ?>;">
                            <?= $profit_usd >= 0 ? '+' : '' ?><?= number_format($profit_percent, 1, '.', ' ') ?>%
                        </div>
                        
                        <div style="display: flex; justify-content: space-between;  border-top: 1px solid rgba(0,0,0,0.05);">
                            <div>
                                <div style="font-size: 10px; color: #6b7a8f;">Прибыль</div>
                                <div style="font-weight: 600; font-size: 13px; color: <?= $profit_usd >= 0 ? '#2e7d32' : '#c62828' ?>;">
                                    <?= $profit_usd >= 0 ? '+' : '' ?><?= number_format($profit_usd, 2, '.', ' ') ?> $
                                </div>
                                <div style="font-size: 10px; color: #6b7a8f;">
                                    <?= $profit_rub >= 0 ? '+' : '' ?><?= number_format($profit_rub, 0, '.', ' ') ?> ₽
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 10px; color: #6b7a8f;">Вложено</div>
                                <div style="font-weight: 600; font-size: 13px;"><?= number_format($total_invested_usd, 2, '.', ' ') ?> $</div>
                                <div style="font-size: 10px; color: #6b7a8f;"><?= number_format($total_invested_rub, 0, '.', ' ') ?> ₽</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="padding: 10px 16px; border-radius: 12px; border: 1px solid var(--border-color);">
                        <div style="font-size: 12px; color: #1976d2; font-weight: 500;">Рубли</div>
                        <div style="font-weight: 600; margin-top: 2px;"><?= number_format($rub_in_usd, 2, '.', ' ') ?> $</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= number_format($rub_amount_display, 0, '.', ' ') ?> ₽</div>
                    </div>
                    
                    <div style="padding: 10px 16px; border-radius: 12px; border: 1px solid var(--border-color);">
                        <div style="font-size: 12px; color: #1976d2; font-weight: 500;">Доллары</div>
                        <div style="font-weight: 600; margin-top: 2px;"><?= number_format($usd_amount + $usdt_amount, 2, '.', ' ') ?> $</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= number_format($usd_amount, 2, '.', ' ') ?> USD / <?= number_format($usdt_amount, 2, '.', ' ') ?> USDT</div>
                    </div>
                    
                    <div style="padding: 10px 16px; border-radius: 12px; border: 1px solid var(--border-color);">
                        <div style="font-size: 12px; color: #1976d2; font-weight: 500;">Инвестиции</div>
                        <div style="font-weight: 600; margin-top: 2px;"><?= number_format($investments_value, 2, '.', ' ') ?> $</div>
                        <div style="font-size: 11px; color: #6b7a8f;"><?= number_format($investments_rub, 0, '.', ' ') ?> ₽</div>
                    </div>
                </div>
            </div>
            
            <!-- Карточки аналитики в header -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px; width: 100%;">
                
                <!-- Карточка распределения по площадкам -->
                <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-building" style="color: #4a9eff; font-size: 18px;"></i>
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-secondary);; margin: 0;">Площадки</h4>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php 
                        if (isset($platform_distribution) && !empty($platform_distribution)):
                            $platform_colors = ['#4a9eff', '#1a5cff', '#ff9f4a', '#2ecc71', '#e74c3c'];
                            $top_platforms = $platform_distribution; // показать все
                            foreach ($top_platforms as $index => $platform): 
                                $percentage = $total_usd > 0 ? round(($platform['total_value_usd'] / $total_usd) * 100, 1) : 0;
                                $value_usd = $platform['total_value_usd'];
                                $value_rub = $value_usd * $usd_rub_rate;
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center; cursor: pointer;" 
                            onclick="openPlatformAssetsModal(<?= $platform['platform_id'] ?>, '<?= htmlspecialchars($platform['platform_name'], ENT_QUOTES) ?>')"
                            onmouseover="this.style.opacity='0.8'" 
                            onmouseout="this.style.opacity='1'">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="width: 8px; height: 8px; border-radius: 4px; background: <?= $platform_colors[$index % count($platform_colors)] ?>;"></div>
                                <span style="font-size: 13px; color: var(--text-secondary);; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($platform['platform_name']) ?>
                                </span>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary);; text-align: right;">$<?= number_format($value_usd, 0) ?></span>
                            <span style="font-size: 12px; color: #6b7a8f; text-align: right;">/ <?= number_format($value_rub, 0) ?> ₽</span>
                        </div>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                        <div style="color: #6b7a8f; font-size: 13px; text-align: center;">Нет данных</div>
                        <?php endif; ?>
                    </div>
                </div>
                                
                <!-- Карточка распределения по сетям -->
                <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-network-wired" style="color: #ff9f4a; font-size: 18px;"></i>
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-secondary);; margin: 0;">Сети</h4>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php 
                        if (isset($network_distribution_array) && !empty($network_distribution_array)):
                            $network_colors = ['#14b8a6', '#8b5cf6', '#ec4899', '#f59e0b', '#3b82f6', '#ef4444'];
                            $top_networks = array_slice($network_distribution_array, 0, 5);
                            foreach ($top_networks as $index => $network): 
                                $percentage = $total_crypto > 0 ? round(($network['total_value_usd'] / $total_crypto) * 100, 1) : 0;
                                $value_usd = $network['total_value_usd'];
                                $value_rub = $value_usd * $usd_rub_rate;
                                $network_icon = 'fa-network-wired';
                                $network_name = strtoupper($network['network']);
                                if (strpos($network_name, 'ERC') !== false) $network_icon = 'fa-ethereum';
                                else if (strpos($network_name, 'BEP') !== false) $network_icon = 'fa-bolt';
                                else if (strpos($network_name, 'TRC') !== false) $network_icon = 'fa-t';
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center; cursor: pointer;" 
                            onclick="openNetworkAssetsModal('<?= htmlspecialchars($network['network'], ENT_QUOTES) ?>')"
                            onmouseover="this.style.opacity='0.8'" 
                            onmouseout="this.style.opacity='1'">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <i class="fab <?= $network_icon ?>" style="color: <?= $network_colors[$index % count($network_colors)] ?>; width: 16px; font-size: 12px;"></i>
                                <span style="font-size: 13px; color: var(--text-secondary);; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($network['network']) ?></span>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary);; text-align: right;">$<?= number_format($value_usd, 0) ?></span>
                            <span style="font-size: 12px; color: #6b7a8f; text-align: right;">/ <?= number_format($value_rub, 0) ?> ₽</span>
                        </div>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                        <div style="color: #6b7a8f; font-size: 13px; text-align: center;">Нет данных</div>
                        <?php endif; ?>
                    </div>
                </div>
                                
                <!-- Карточка типов платформ -->
                <div style="background: white; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                        <i class="fas fa-chart-pie" style="color: #2ecc71; font-size: 18px;"></i>
                        <h4 style="font-size: 14px; font-weight: 600; color: var(--text-secondary);; margin: 0;">Типы платформ</h4>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php
                        if (isset($platform_distribution) && !empty($platform_distribution)):
                            $platform_types = [];
                            foreach ($platform_distribution as $platform) {
                                $type = $platform['platform_type'];
                                if (!isset($platform_types[$type])) {
                                    $platform_types[$type] = 0;
                                }
                                $platform_types[$type] += $platform['total_value_usd'];
                            }
                            
                            $type_colors = [
                                'exchange' => '#4a9eff',
                                'bank' => '#2ecc71',
                                'wallet' => '#ff9f4a',
                                'broker' => '#9b59b6',
                                'other' => '#95a5a6'
                            ];
                            
                            $type_names = [
                                'exchange' => 'Биржи',
                                'bank' => 'Банки',
                                'wallet' => 'Кошельки',
                                'broker' => 'Брокеры',
                                'other' => 'Другое'
                            ];
                            
                            arsort($platform_types);
                            $top_types = array_slice($platform_types, 0, 3, true);
                            
                            foreach ($top_types as $type => $value_usd): 
                                $percentage = $total_usd > 0 ? round(($value_usd / $total_usd) * 100, 1) : 0;
                                $color = $type_colors[$type] ?? '#95a5a6';
                                $value_rub = $value_usd * $usd_rub_rate;
                        ?>
                        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="width: 8px; height: 8px; border-radius: 4px; background: <?= $color ?>;"></div>
                                <span style="font-size: 13px; color: var(--text-secondary);;"><?= $type_names[$type] ?? ucfirst($type) ?></span>
                            </div>
                            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary);; text-align: right;">$<?= number_format($value_usd, 0) ?></span>
                            <span style="font-size: 12px; color: #6b7a8f; text-align: right;">/ <?= number_format($value_rub, 0) ?> ₽</span>
                        </div>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                        <div style="color: #6b7a8f; font-size: 13px; text-align: center;">Нет данных</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>        

        <!-- КОНТЕЙНЕР С КАРТОЧКАМИ (резиновая верстка) -->
        <div class="cards-container">
            <!-- Карточка мои активы -->
            <div class="card card-investments">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-coins"></i> Мои активы</h3>
                    <span class="stat-badge"><?= count($my_assets) ?> активов</span>
                </div>
                
                <div class="investments-table-wrapper">
                    <table class="investments-table-new">
                        <thead>
                            <tr>
                                <th>Актив</th>
                                <th class="text-right">Количество</th>
                                <th class="text-right">Средняя цена<br><span class="table-subtitle">покупки</span></th>
                                <th class="text-right">Стоимость<br><span class="table-subtitle">покупки</span></th>
                                <th class="text-right">Текущая цена</th>
                                <th class="text-right">Текущая<br><span class="table-subtitle">стоимость</span></th>
                                <th class="text-right">Доходность</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Подготавливаем запрос для истории один раз вне цикла
                            $stmt_history = $pdo->prepare("
                                (SELECT 
                                    t.operation_date as date,
                                    t.quantity,
                                    t.price,
                                    t.price_currency,
                                    p.name as platform,
                                    'buy' as operation_type,
                                    CONCAT('Покупка ', a.symbol) as description
                                FROM trades t
                                LEFT JOIN platforms p ON t.platform_id = p.id
                                JOIN assets a ON t.asset_id = a.id
                                WHERE t.asset_id = ? AND t.operation_type = 'buy')
                                
                                UNION ALL
                                
                                (SELECT 
                                    t.operation_date as date,
                                    t.quantity,
                                    t.price,
                                    t.price_currency,
                                    p.name as platform,
                                    'sell' as operation_type,
                                    CONCAT('Продажа ', a.symbol) as description
                                FROM trades t
                                LEFT JOIN platforms p ON t.platform_id = p.id
                                JOIN assets a ON t.asset_id = a.id
                                WHERE t.asset_id = ? AND t.operation_type = 'sell')
                                
                                ORDER BY date DESC
                            ");
                            
                            foreach ($my_assets as $asset): 
                                // Пропускаем, если нет символа
                                if (empty($asset['symbol'])) continue;
                                
                                // Получаем историю для текущего актива
                                $stmt_history->execute([$asset['id'] ?? 0, $asset['id'] ?? 0]);
                                $asset_history = $stmt_history->fetchAll();
                                
                                // Определяем иконку для актива
                                $icon_map = [
                                    'RUB' => '₽', 'USD' => '$', 'EUR' => '€',
                                    'BTC' => '₿', 'ETH' => 'Ξ', 'USDT' => '₮',
                                    'SOL' => '◎', 'BNB' => 'ⓑ'
                                ];
                                $icon = $icon_map[$asset['symbol']] ?? substr($asset['symbol'], 0, 2);
                                
                                // Определяем валюту для отображения
                                $display_currency = ($asset['type'] == 'crypto' && $asset['symbol'] != 'USDT') ? $asset['symbol'] : ($asset['currency_code'] ?: 'USD');
                                
                                if ($asset['symbol'] == 'RUB') {
                                    $display_currency = 'RUB';
                                } elseif ($asset['type'] == 'stock' && $asset['currency_code'] == 'RUB') {
                                    $display_currency = 'RUB';
                                } elseif ($asset['type'] == 'stock') {
                                    $display_currency = $asset['currency_code'] ?: 'USD';
                                }
                            ?>
                            <tr onclick='showAssetDetails("<?= $asset['symbol'] ?>", <?= $asset['id'] ?? 0 ?>)' style="cursor: pointer;">
                                <td>
                                    <div class="asset-info">
                                        <div class="asset-icon"><?= $icon ?></div>
                                        <div>
                                            <div class="asset-symbol"><?= htmlspecialchars($asset['symbol']) ?></div>
                                            <div class="asset-name"><?= htmlspecialchars($asset['name'] ?? $asset['symbol']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <?= $asset['quantity_formatted'] ?? number_format($asset['total_quantity'] ?? 0, 0, '.', ' ') ?>
                                    <span class="asset-symbol-small"><?= htmlspecialchars($display_currency) ?></span>
                                </td>
                                <td class="text-right">
                                    <?= $asset['avg_price_formatted'] ?? '—' ?>
                                    <?php if (($asset['avg_price'] ?? 0) > 0): ?>
                                    <span class="asset-symbol-small"><?= htmlspecialchars($asset['currency_code'] ?: 'USD') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?= $asset['purchase_cost_formatted'] ?? '—' ?>
                                    <?php if (($asset['purchase_cost'] ?? 0) > 0): ?>
                                    <span class="asset-symbol-small"><?= htmlspecialchars($asset['currency_code'] ?: 'USD') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?= $asset['current_price_formatted'] ?? '—' ?>
                                    <?php if (($asset['current_price'] ?? 0) > 0): ?>
                                    <span class="asset-symbol-small"><?= htmlspecialchars($asset['current_price_currency'] ?: 'USD') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?= $asset['current_value_formatted'] ?? '—' ?>
                                    <span class="asset-symbol-small"><?= htmlspecialchars($asset['currency_code'] ?: 'USD') ?></span>
                                </td>
                                <td class="text-right <?= $asset['profit_class'] ?? 'profit-neutral' ?>">
                                    <?php if (($asset['profit'] ?? 0) != 0 || ($asset['purchase_cost'] ?? 0) > 0): ?>
                                        <span class="profit-value"><?= $asset['profit_formatted'] ?? '0' ?> <?= htmlspecialchars($asset['currency_code'] ?: 'USD') ?></span>
                                        <span class="profit-percent">(<?= $asset['profit_percent_formatted'] ?? '0' ?>%)</span>
                                    <?php else: ?>
                                        <span class="profit-value">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($my_assets)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-tertiary);">
                                    <i class="fas fa-box-open" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                                    Нет активов в портфеле
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Карточка структуры портфеля -->
            <div class="card card-structure">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie"></i> Структура портфеля</h3>
                    <span class="stat-badge">По типам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    if ($portfolio_structure[0]['category'] !== 'Нет данных') {
                        $colors = ['#4a9eff', '#1a5cff', '#ff9f4a', '#2ecc71', '#95a5a6', '#e74c3c', '#9b59b6'];
                        $gradient = [];
                        $current = 0;
                        
                        foreach ($portfolio_structure as $index => $item) {
                            $gradient[] = $colors[$index % count($colors)] . ' ' . $current . '% ' . ($current + $item['percentage']) . '%';
                            $current += $item['percentage'];
                        }
                        ?>
                        <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                        <div class="chart-legend">
                            <?php foreach ($portfolio_structure as $index => $item): ?>
                            <div class="legend-item" style="align-items: flex-start;">
                                <span class="legend-color" style="width: 12px; height: 12px; background: <?= $colors[$index % count($colors)] ?>; border-radius: 4px; margin-top: 4px;"></span>
                                <span style="flex: 1;"><?= htmlspecialchars($item['category']) ?></span>
                                <span class="legend-value" style="text-align: right;">
                                    <div><?= $item['percentage'] ?>%</div>
                                    <?php if ($item['category'] == 'Рубли'): ?>
                                        <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($item['value'] ?? 0, 0, '.', ' ') ?> / <?= number_format($rub_amount_display, 0, '.', ' ') ?> ₽</div>
                                    <?php else: ?>
                                        <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($item['value'] ?? 0, 0, '.', ' ') ?></div>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php } else { ?>
                        <div class="pie" style="background: conic-gradient(#95a5a6 0% 100%);"></div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color" style="background: #95a5a6;"></span>
                                <span>Нет данных</span>
                                <span class="legend-value">0%</span>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Карточка крипто (только если есть данные) -->
            <?php if ($total_usdt_bought > 1 || $btc_cost > 0 || $eth_cost > 0 || $altcoins_cost > 0 || $stablecoins_left > 0): ?>
            <div class="card card-crypto">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #ff9f4a;"></i> Крипто</h3>
                    <span class="stat-badge">По активам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    $gradient_parts = [];
                    $current = 0;
                    $colors = ['#f7931a', '#627eea', '#14b8a6', '#a5a5a5'];
                    $segments = [];
                    
                    if ($btc_cost > 0) {
                        $segments[] = [
                            'name' => 'BTC', 
                            'value' => $btc_cost, 
                            'percent' => $btc_percent, 
                            'color' => '#f7931a', 
                            'icon' => 'fab fa-bitcoin',
                            'type' => 'btc'  // добавляем тип для идентификации
                        ];
                    }
                    if ($eth_cost > 0) {
                        $segments[] = [
                            'name' => 'ETH', 
                            'value' => $eth_cost, 
                            'percent' => $eth_percent, 
                            'color' => '#627eea', 
                            'icon' => 'fab fa-ethereum',
                            'type' => 'eth'
                        ];
                    }
                    if ($altcoins_cost > 0) {
                        $segments[] = [
                            'name' => 'Альткоины',  // переводим на русский
                            'value' => $altcoins_cost, 
                            'percent' => $altcoins_percent, 
                            'color' => '#14b8a6', 
                            'icon' => 'fas fa-chart-line',
                            'type' => 'altcoins'  // добавляем тип для идентификации
                        ];
                    }
                    if ($stablecoins_left > 0) {
                        $segments[] = [
                            'name' => 'Стейблкоины',  // переводим на русский
                            'value' => $stablecoins_left, 
                            'percent' => $stablecoins_percent, 
                            'color' => '#a5a5a5', 
                            'icon' => 'fas fa-coins',
                            'type' => 'stablecoins'  // добавляем тип для идентификации
                        ];
                    }
                    
                    foreach ($segments as $index => $segment) {
                        $gradient_parts[] = $segment['color'] . ' ' . $current . '% ' . ($current + $segment['percent']) . '%';
                        $current += $segment['percent'];
                    }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient_parts) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($segments as $segment): 
                            // Определяем, нужно ли делать элемент кликабельным
                            $isClickable = ($segment['type'] === 'altcoins' || $segment['type'] === 'stablecoins');
                            $onclickAttr = '';
                            $cursorStyle = '';
                            
                            if ($isClickable) {
                                $modalType = $segment['type'];
                                $modalName = $segment['name'];
                                $onclickAttr = "onclick=\"openCryptoTypeModal('{$modalType}', '{$modalName}')\"";
                                $cursorStyle = "cursor: pointer;";
                            }
                        ?>
                        <div class="legend-item" style="align-items: flex-start; <?= $cursorStyle ?>" 
                            <?= $onclickAttr ?>
                            <?php if ($isClickable): ?>
                            onmouseover="this.style.opacity='0.8'" 
                            onmouseout="this.style.opacity='1'"
                            <?php endif; ?>>
                            <span class="legend-color" style="width: 12px; height: 12px; background: <?= $segment['color'] ?>; margin-top: 4px;"></span>
                            <span style="flex: 1; display: flex; align-items: center; gap: 6px;">
                                <i class="<?= $segment['icon'] ?>" style="color: <?= $segment['color'] ?>; width: 16px;"></i>
                                <?= $segment['name'] ?>
                            </span>
                            <span class="legend-value" style="text-align: right;">
                                <div><?= $segment['percent'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($segment['value'], 0, '.', ' ') ?></div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <div class="legend-item" style="border-top: 1px solid #edf2f7; margin-top: 8px; padding-top: 8px;">
                            <span style="font-weight: 600;">Всего</span>
                            <span class="legend-value" style="font-weight: 600;">$<?= number_format($total_crypto_value, 0, '.', ' ') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Карточка фондовый EN (только если есть данные) -->
            <?php if (!empty($en_sectors)): ?>
            <div class="card card-en-stocks">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #4a9eff;"></i> Фондовый (EN)</h3>
                    <span class="stat-badge">По секторам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    $colors = ['#4a9eff', '#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#6b7280'];
                    $gradient = [];
                    $current = 0;
                    
                    // Рассчитываем общую стоимость для фондового EN
                    $total_en_value = 0;
                    foreach ($en_sectors as $sector) {
                        $total_en_value += $sector['value_usd'];
                    }
                    
                    // Формируем градиент на основе процентов
                    foreach ($en_sectors as $index => $sector) {
                        $gradient[] = $colors[$index % count($colors)] . ' ' . $current . '% ' . ($current + $sector['percentage']) . '%';
                        $current += $sector['percentage'];
                    }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($en_sectors as $index => $sector): ?>
                        <div class="legend-item" style="align-items: flex-start; cursor: pointer;" 
                            onclick="openSectorAssetsModal('<?= htmlspecialchars($sector['original_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($sector['sector_name'], ENT_QUOTES) ?>')"
                            onmouseover="this.style.opacity='0.8'" 
                            onmouseout="this.style.opacity='1'">
                            <span class="legend-color" style="width: 12px; height: 12px; background: <?= $colors[$index % count($colors)] ?>; border-radius: 4px; margin-top: 4px;"></span>
                            <span style="flex: 1;"><?= htmlspecialchars($sector['sector_name']) ?></span>
                            <span class="legend-value" style="text-align: right;">
                                <div><?= $sector['percentage'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$<?= number_format($sector['value_usd'], 0, '.', ' ') ?></div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- БЛОК "ВСЕГО" -->
                        <div class="legend-item" style="border-top: 1px solid #edf2f7; margin-top: 8px; padding-top: 8px;">
                            <span style="font-weight: 600;">Всего</span>
                            <span class="legend-value" style="font-weight: 600; text-align: right;">
                                $<?= number_format($total_en_value, 0, '.', ' ') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Карточка фондовый РФ (только если есть данные) -->
            <?php if ($has_ru_data && !empty($ru_sectors)): ?>
            <div class="card card-ru-stocks">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #2ecc71;"></i> Фондовый (РФ)</h3>
                    <span class="stat-badge">По секторам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    $colors = ['#2ecc71', '#3498db', '#f1c40f', '#e67e22', '#95a5a6', '#e74c3c', '#9b59b6', '#1abc9c'];
                    $gradient = [];
                    $current = 0;
                    
                    foreach ($ru_sectors as $index => $sector) {
                        $gradient[] = $colors[$index % count($colors)] . ' ' . $current . '% ' . ($current + $sector['percentage']) . '%';
                        $current += $sector['percentage'];
                    }
                    
                    // Рассчитываем общую стоимость в рублях
                    $total_ru_rub = 0;
                    foreach ($ru_sectors as $sector) {
                        $total_ru_rub += $sector['value_usd'] * $usd_rub_rate;
                    }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($ru_sectors as $index => $sector): ?>
                        <div class="legend-item" style="align-items: flex-start;">
                            <span class="legend-color" style="width: 12px; height: 12px; background: <?= $colors[$index % count($colors)] ?>; border-radius: 4px; margin-top: 4px;"></span>
                            <span style="flex: 1;"><?= htmlspecialchars($sector['sector_name']) ?></span>
                            <span class="legend-value" style="text-align: right;">
                                <div><?= $sector['percentage'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">
                                    $<?= number_format($sector['value_usd'], 0, '.', ' ') ?>
                                </div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- БЛОК "ВСЕГО" -->
                        <div class="legend-item" style="border-top: 1px solid #edf2f7; margin-top: 8px; padding-top: 8px;">
                            <span style="font-weight: 600;">Всего</span>
                            <span class="legend-value" style="font-weight: 600; text-align: right;">
                                $<?= number_format($total_ru_value_usd, 0, '.', ' ') ?>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">
                                    <?= number_format($total_ru_rub, 0, '.', ' ') ?> ₽
                                </div>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Карточка вклады (только если есть данные) -->
            <?php if (!empty($deposit_currencies)): ?>
            <div class="card card-deposits">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie" style="color: #2ecc71;"></i> Вклады</h3>
                    <span class="stat-badge">По валютам</span>
                </div>
                <div class="pie-chart">
                    <?php
                    $colors = ['#2ecc71', '#3498db', '#f1c40f', '#e67e22', '#95a5a6'];
                    $gradient = [];
                    $current = 0;
                    
                    foreach ($deposit_currencies as $index => $currency) {
                        $gradient[] = $colors[$index % count($colors)] . ' ' . $current . '% ' . ($current + $currency['percentage']) . '%';
                        $current += $currency['percentage'];
                    }
                    ?>
                    <div class="pie" style="background: conic-gradient(<?= implode(', ', $gradient) ?>);"></div>
                    <div class="chart-legend">
                        <?php foreach ($deposit_currencies as $index => $currency): ?>
                        <div class="legend-item" style="align-items: flex-start;">
                            <span class="legend-color" style="width: 12px; height: 12px; background: <?= $colors[$index % count($colors)] ?>; border-radius: 4px; margin-top: 4px;"></span>
                            <span style="flex: 1;"><?= htmlspecialchars($currency['name'] ?? $currency['currency_code']) ?> (<?= $currency['currency_code'] ?>)</span>
                            <span class="legend-value" style="text-align: right;">
                                <div><?= $currency['percentage'] ?>%</div>
                                <div style="font-size: 11px; color: #6b7a8f; font-weight: normal;">$0</div>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Карточка последние операции -->
            <div class="card card-operations" id="operationsContainer">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history"></i> Последние операции</h3>
                    <a href="operations.php" class="all-ops-btn" style="
                        flex: 0 1 auto;
                        justify-content: center;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        border: 1px solid transparent;
                        background: #f0f3f7;
                        color: #6b7a8f;
                        font-weight: 500;
                        padding: 8px 16px;
                        border-radius: 12px;
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        transform: translateY(0);
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
                        font-size: 14px;
                        text-decoration: none;
                    " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 16px rgba(26, 92, 255, 0.15)'; this.style.background='white'; this.style.borderColor='#1a5cff'; this.style.color='#1a5cff';" 
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(0, 0, 0, 0.02)'; this.style.background='#f0f3f7'; this.style.borderColor='transparent'; this.style.color='#6b7a8f';">
                        <i class="fas fa-list-ul"></i> Все операции
                    </a>
                </div>
                
                <div id="operationsList">
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
                
                <div id="paginationControls"></div>
            </div>

            <!-- Карточка лимитные ордера -->
            <div class="card card-orders">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clock"></i> Лимитные ордера</h3>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="stat-badge"><?= count($orders) ?></span>
                    </div>
                </div>

                <!-- Кнопка добавления вверху списка -->
                <div style="margin-bottom: 15px; text-align: center;">
                    <button class="add-order-btn" onclick="openLimitOrderModal()">
                        <i class="fas fa-plus-circle"></i> Создать новый ордер
                    </button>
                </div>
                
                <div id="limitOrdersList">
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): 
                            // Рассчитываем прогресс (если нужно)
                            $progress = 0;
                            $progressClass = '';
                            $daysLeft = 0;
                            if ($order['expiry_date']) {
                                $daysLeft = (strtotime($order['expiry_date']) - time()) / (60 * 60 * 24);
                                if ($daysLeft < 1) {
                                    $progressClass = 'danger';
                                } elseif ($daysLeft < 3) {
                                    $progressClass = 'warning';
                                }
                            }
                        ?>
                        <div class="order-card" id="order-<?= $order['id'] ?>" style="border-left-color: <?= $order['operation_type'] == 'buy' ? '#00a86b' : '#e53e3e' ?>;">
                            <div class="order-exchange">
                                <i class="fas fa-<?= $order['platform_type'] == 'exchange' ? 'chart-line' : 'building' ?>"></i>
                                <?= strtoupper(htmlspecialchars($order['platform_name'])) ?>
                            </div>
                            <div class="order-details">
                                <span class="order-action">
                                    <i class="fas fa-<?= $order['operation_type'] == 'buy' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                    <?= $order['operation_type'] == 'buy' ? 'Покупка' : 'Продажа' ?> <?= htmlspecialchars($order['symbol']) ?>
                                </span>
                                <span class="order-price"><?= number_format($order['limit_price'], 2) ?> <?= $order['price_currency'] ?></span>
                            </div>
                            <div class="order-footer">
                                <span><i class="far fa-clock"></i> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></span>
                                <span><?= number_format($order['quantity'], 4) ?> шт</span>
                            </div>
                            <?php if ($order['expiry_date']): ?>
                            <div class="order-progress">
                                <div class="order-progress-bar <?= $progressClass ?>" style="width: <?= min(100, (1 - $daysLeft/30) * 100) ?>%;"></div>
                            </div>
                            <div style="font-size: 11px; color: #6b7a8f; margin-top: 5px; text-align: right;">
                                <i class="far fa-hourglass"></i> до <?= date('d.m.Y', strtotime($order['expiry_date'])) ?> (<?= round($daysLeft) ?> дн.)
                            </div>
                            <?php endif; ?>
                            
                            <!-- Кнопки действий -->
                            <div style="display: flex; gap: 8px; margin-top: 12px; justify-content: flex-end;">
                                <button class="quick-platform-btn" onclick="showExecuteConfirmation(<?= $order['id'] ?>)" 
                                        style="background: #00a86b; color: white; border: none; min-width: 100px;">
                                    <i class="fas fa-check-circle"></i> Исполнить
                                </button>
                                <button class="quick-platform-btn" onclick="showCancelConfirmation(<?= $order['id'] ?>)" 
                                        style="background: #e53e3e; color: white; border: none; min-width: 100px;">
                                    <i class="fas fa-times-circle"></i> Отменить
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="order-empty">
                            <i class="fas fa-clock"></i>
                            <p>Нет активных лимитных ордеров</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Карточка план действий -->
            <div class="card card-plan" style="display: none;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-check-circle"></i> План действий</h3>
                    <?php
                    $completed = 0;
                    foreach ($action_plan as $item) {
                        if ($item['is_completed']) $completed++;
                    }
                    $total = count($action_plan);
                    ?>
                    <span class="stat-badge"><?= $completed ?>/<?= $total ?> выполнено</span>
                </div>
                <?php foreach ($action_plan as $item): ?>
                <div class="checklist-item">
                    <div class="checklist-checkbox <?= $item['is_completed'] ? 'checked' : '' ?>"></div>
                    <span class="checklist-text <?= $item['is_completed'] ? 'completed' : '' ?>">
                        <?= htmlspecialchars($item['title']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Карточка заметки -->
            <div class="card card-notes">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sticky-note"></i> Заметки</h3>
                    <div style="display: flex; gap: 8px;">
                        <button class="view-archive-btn" onclick="openArchivedNotesModal()">
                            <i class="fas fa-archive"></i> Архив
                        </button>
                    </div>
                </div>
                
                <div id="notesList">
                    <div style="text-align: center; padding: 20px; color: #6b7a8f;">
                        <i class="fas fa-spinner fa-spin"></i> Загрузка...
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
// ============================================================================
// ДАННЫЕ ИЗ PHP
// ============================================================================
const platformsData = <?= $platforms_json ?>;
const assetsData = <?= $assets_json ?>;
const allCurrencies = <?= $currencies_json ?>;
const fiatCurrencies = <?= $fiat_currencies_json ?>;
const usdRubRate = <?= $usd_rub_rate ?>;
// Устанавливаем текущую тему при загрузке
document.body.className = '<?= $current_theme === 'dark' ? 'dark-theme' : '' ?>';
// Сети из базы данных
const networksFromDB = <?= $networks_json ?>;
// Данные по активам площадок из PHP
const platformAssetsData = <?= $platform_assets_json ?>;
// Данные по активам сетей из PHP
const networkAssetsData = <?= $network_assets_json ?>;
// Данные по активам секторов из PHP
const sectorAssetsData = <?= $sector_assets_json ?>;
// Данные по активам типов криптовалют из PHP
const cryptoTypeAssetsData = <?= $crypto_type_assets_json ?>;
</script>
<script src="js.js"></script>
</html>