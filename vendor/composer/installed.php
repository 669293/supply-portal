<?php return array(
    'root' => array(
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'reference' => NULL,
        'name' => '__root__',
        'dev' => true,
    ),
    'versions' => array(
        '__root__' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'reference' => NULL,
            'dev_requirement' => false,
        ),
        'doctrine/inflector' => array(
            'pretty_version' => '2.0.3',
            'version' => '2.0.3.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../doctrine/inflector',
            'aliases' => array(),
            'reference' => '9cf661f4eb38f7c881cac67c75ea9b00bf97b210',
            'dev_requirement' => true,
        ),
        'mpdf/mpdf' => array(
            'pretty_version' => 'dev-php8-support',
            'version' => 'dev-php8-support',
            'type' => 'library',
            'install_path' => __DIR__ . '/../mpdf/mpdf',
            'aliases' => array(),
            'reference' => '4dff53e7f6714c88ae1803cf89d92941e6d29702',
            'dev_requirement' => false,
        ),
        'myclabs/deep-copy' => array(
            'pretty_version' => '1.10.2',
            'version' => '1.10.2.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../myclabs/deep-copy',
            'aliases' => array(),
            'reference' => '776f831124e9c62e1a2c601ecc52e776d8bb7220',
            'dev_requirement' => false,
            'replaced' => array(
                0 => '1.10.2',
            ),
        ),
        'nikic/php-parser' => array(
            'pretty_version' => 'v4.12.0',
            'version' => '4.12.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../nikic/php-parser',
            'aliases' => array(),
            'reference' => '6608f01670c3cc5079e18c1dab1104e002579143',
            'dev_requirement' => true,
        ),
        'paragonie/random_compat' => array(
            'pretty_version' => 'v9.99.100',
            'version' => '9.99.100.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../paragonie/random_compat',
            'aliases' => array(),
            'reference' => '996434e5492cb4c3edcb9168db6fbb1359ef965a',
            'dev_requirement' => false,
        ),
        'psr/cache' => array(
            'pretty_version' => '2.0.0',
            'version' => '2.0.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../psr/cache',
            'aliases' => array(),
            'reference' => '213f9dbc5b9bfbc4f8db86d2838dc968752ce13b',
            'dev_requirement' => true,
        ),
        'psr/cache-implementation' => array(
            'dev_requirement' => true,
            'provided' => array(
                0 => '1.0|2.0',
            ),
        ),
        'psr/container' => array(
            'pretty_version' => '1.1.1',
            'version' => '1.1.1.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../psr/container',
            'aliases' => array(),
            'reference' => '8622567409010282b7aeebe4bb841fe98b58dcaf',
            'dev_requirement' => true,
        ),
        'psr/container-implementation' => array(
            'dev_requirement' => true,
            'provided' => array(
                0 => '1.0',
            ),
        ),
        'psr/event-dispatcher' => array(
            'pretty_version' => '1.0.0',
            'version' => '1.0.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../psr/event-dispatcher',
            'aliases' => array(),
            'reference' => 'dbefd12671e8a14ec7f180cab83036ed26714bb0',
            'dev_requirement' => true,
        ),
        'psr/event-dispatcher-implementation' => array(
            'dev_requirement' => true,
            'provided' => array(
                0 => '1.0',
            ),
        ),
        'psr/log' => array(
            'pretty_version' => '1.1.4',
            'version' => '1.1.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../psr/log',
            'aliases' => array(),
            'reference' => 'd49695b909c3b7628b6289db5479a1c204601f11',
            'dev_requirement' => false,
        ),
        'psr/log-implementation' => array(
            'dev_requirement' => true,
            'provided' => array(
                0 => '1.0|2.0',
            ),
        ),
        'psr/simple-cache-implementation' => array(
            'dev_requirement' => true,
            'provided' => array(
                0 => '1.0',
            ),
        ),
        'setasign/fpdi' => array(
            'pretty_version' => 'v2.3.6',
            'version' => '2.3.6.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../setasign/fpdi',
            'aliases' => array(),
            'reference' => '6231e315f73e4f62d72b73f3d6d78ff0eed93c31',
            'dev_requirement' => false,
        ),
        'symfony/cache' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/cache',
            'aliases' => array(),
            'reference' => '944db6004fc374fbe032d18e07cce51cc4e1e661',
            'dev_requirement' => true,
        ),
        'symfony/cache-contracts' => array(
            'pretty_version' => 'v2.4.0',
            'version' => '2.4.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/cache-contracts',
            'aliases' => array(),
            'reference' => 'c0446463729b89dd4fa62e9aeecc80287323615d',
            'dev_requirement' => true,
        ),
        'symfony/cache-implementation' => array(
            'dev_requirement' => true,
            'provided' => array(
                0 => '1.0|2.0',
            ),
        ),
        'symfony/config' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/config',
            'aliases' => array(),
            'reference' => '4268f3059c904c61636275182707f81645517a37',
            'dev_requirement' => true,
        ),
        'symfony/console' => array(
            'pretty_version' => 'v5.3.6',
            'version' => '5.3.6.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/console',
            'aliases' => array(),
            'reference' => '51b71afd6d2dc8f5063199357b9880cea8d8bfe2',
            'dev_requirement' => true,
        ),
        'symfony/dependency-injection' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/dependency-injection',
            'aliases' => array(),
            'reference' => '5a825e4b386066167a8b55487091cb62beec74c2',
            'dev_requirement' => true,
        ),
        'symfony/deprecation-contracts' => array(
            'pretty_version' => 'v2.4.0',
            'version' => '2.4.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/deprecation-contracts',
            'aliases' => array(),
            'reference' => '5f38c8804a9e97d23e0c8d63341088cd8a22d627',
            'dev_requirement' => true,
        ),
        'symfony/error-handler' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/error-handler',
            'aliases' => array(),
            'reference' => '281f6c4660bcf5844bb0346fe3a4664722fe4c73',
            'dev_requirement' => true,
        ),
        'symfony/event-dispatcher' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/event-dispatcher',
            'aliases' => array(),
            'reference' => 'f2fd2208157553874560f3645d4594303058c4bd',
            'dev_requirement' => true,
        ),
        'symfony/event-dispatcher-contracts' => array(
            'pretty_version' => 'v2.4.0',
            'version' => '2.4.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/event-dispatcher-contracts',
            'aliases' => array(),
            'reference' => '69fee1ad2332a7cbab3aca13591953da9cdb7a11',
            'dev_requirement' => true,
        ),
        'symfony/event-dispatcher-implementation' => array(
            'dev_requirement' => true,
            'provided' => array(
                0 => '2.0',
            ),
        ),
        'symfony/filesystem' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/filesystem',
            'aliases' => array(),
            'reference' => '343f4fe324383ca46792cae728a3b6e2f708fb32',
            'dev_requirement' => true,
        ),
        'symfony/finder' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/finder',
            'aliases' => array(),
            'reference' => '17f50e06018baec41551a71a15731287dbaab186',
            'dev_requirement' => true,
        ),
        'symfony/framework-bundle' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'symfony-bundle',
            'install_path' => __DIR__ . '/../symfony/framework-bundle',
            'aliases' => array(),
            'reference' => '2c5ed14a5992a2d04dfdb238a5f9589bab0a68d8',
            'dev_requirement' => true,
        ),
        'symfony/http-client-contracts' => array(
            'pretty_version' => 'v2.4.0',
            'version' => '2.4.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/http-client-contracts',
            'aliases' => array(),
            'reference' => '7e82f6084d7cae521a75ef2cb5c9457bbda785f4',
            'dev_requirement' => true,
        ),
        'symfony/http-foundation' => array(
            'pretty_version' => 'v5.3.6',
            'version' => '5.3.6.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/http-foundation',
            'aliases' => array(),
            'reference' => 'a8388f7b7054a7401997008ce9cd8c6b0ab7ac75',
            'dev_requirement' => true,
        ),
        'symfony/http-kernel' => array(
            'pretty_version' => 'v5.3.6',
            'version' => '5.3.6.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/http-kernel',
            'aliases' => array(),
            'reference' => '60030f209018356b3b553b9dbd84ad2071c1b7e0',
            'dev_requirement' => true,
        ),
        'symfony/maker-bundle' => array(
            'pretty_version' => 'v1.33.0',
            'version' => '1.33.0.0',
            'type' => 'symfony-bundle',
            'install_path' => __DIR__ . '/../symfony/maker-bundle',
            'aliases' => array(),
            'reference' => 'f093d906c667cba7e3f74487d9e5e55aaf25a031',
            'dev_requirement' => true,
        ),
        'symfony/polyfill-ctype' => array(
            'pretty_version' => 'v1.23.0',
            'version' => '1.23.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/polyfill-ctype',
            'aliases' => array(),
            'reference' => '46cd95797e9df938fdd2b03693b5fca5e64b01ce',
            'dev_requirement' => true,
        ),
        'symfony/polyfill-intl-grapheme' => array(
            'pretty_version' => 'v1.23.1',
            'version' => '1.23.1.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/polyfill-intl-grapheme',
            'aliases' => array(),
            'reference' => '16880ba9c5ebe3642d1995ab866db29270b36535',
            'dev_requirement' => true,
        ),
        'symfony/polyfill-intl-normalizer' => array(
            'pretty_version' => 'v1.23.0',
            'version' => '1.23.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/polyfill-intl-normalizer',
            'aliases' => array(),
            'reference' => '8590a5f561694770bdcd3f9b5c69dde6945028e8',
            'dev_requirement' => true,
        ),
        'symfony/polyfill-mbstring' => array(
            'pretty_version' => 'v1.23.1',
            'version' => '1.23.1.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/polyfill-mbstring',
            'aliases' => array(),
            'reference' => '9174a3d80210dca8daa7f31fec659150bbeabfc6',
            'dev_requirement' => true,
        ),
        'symfony/polyfill-php73' => array(
            'pretty_version' => 'v1.23.0',
            'version' => '1.23.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/polyfill-php73',
            'aliases' => array(),
            'reference' => 'fba8933c384d6476ab14fb7b8526e5287ca7e010',
            'dev_requirement' => true,
        ),
        'symfony/polyfill-php80' => array(
            'pretty_version' => 'v1.23.1',
            'version' => '1.23.1.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/polyfill-php80',
            'aliases' => array(),
            'reference' => '1100343ed1a92e3a38f9ae122fc0eb21602547be',
            'dev_requirement' => true,
        ),
        'symfony/polyfill-php81' => array(
            'pretty_version' => 'v1.23.0',
            'version' => '1.23.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/polyfill-php81',
            'aliases' => array(),
            'reference' => 'e66119f3de95efc359483f810c4c3e6436279436',
            'dev_requirement' => true,
        ),
        'symfony/routing' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/routing',
            'aliases' => array(),
            'reference' => '0a35d2f57d73c46ab6d042ced783b81d09a624c4',
            'dev_requirement' => true,
        ),
        'symfony/service-contracts' => array(
            'pretty_version' => 'v2.4.0',
            'version' => '2.4.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/service-contracts',
            'aliases' => array(),
            'reference' => 'f040a30e04b57fbcc9c6cbcf4dbaa96bd318b9bb',
            'dev_requirement' => true,
        ),
        'symfony/service-implementation' => array(
            'dev_requirement' => true,
            'provided' => array(
                0 => '1.0|2.0',
            ),
        ),
        'symfony/string' => array(
            'pretty_version' => 'v5.3.3',
            'version' => '5.3.3.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/string',
            'aliases' => array(),
            'reference' => 'bd53358e3eccec6a670b5f33ab680d8dbe1d4ae1',
            'dev_requirement' => true,
        ),
        'symfony/var-dumper' => array(
            'pretty_version' => 'v5.3.6',
            'version' => '5.3.6.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/var-dumper',
            'aliases' => array(),
            'reference' => '3dd8ddd1e260e58ecc61bb78da3b6584b3bfcba0',
            'dev_requirement' => true,
        ),
        'symfony/var-exporter' => array(
            'pretty_version' => 'v5.3.4',
            'version' => '5.3.4.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../symfony/var-exporter',
            'aliases' => array(),
            'reference' => 'b7898a65fc91e7c41de7a88c7db9aee9c0d432f0',
            'dev_requirement' => true,
        ),
    ),
);
