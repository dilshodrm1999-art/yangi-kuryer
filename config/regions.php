<?php
/**
 * O'zbekiston viloyatlari va tumanlari + xarita markazi koordinatalari.
 * Admin panel hudud tanlaganda xarita avtomatik shu nuqtaga o'rnatiladi.
 *
 * Tuzilma:
 *   'Viloyat nomi' => [
 *       'lat' => <markaz kenglik>,
 *       'lng' => <markaz uzunlik>,
 *       'zoom' => <boshlang'ich masshtab>,
 *       'districts' => ['Tuman 1', 'Tuman 2', ...]
 *   ]
 */

function uz_regions(): array
{
    return [
        'Toshkent shahri' => [
            'lat' => 41.311100, 'lng' => 69.279700, 'zoom' => 12,
            'districts' => [
                'Bektemir', 'Chilonzor', 'Mirobod', 'Mirzo Ulug\'bek', 'Olmazor',
                'Sergeli', 'Shayxontohur', 'Uchtepa', 'Yakkasaroy', 'Yashnobod',
                'Yunusobod', 'Yangihayot',
            ],
        ],
        'Toshkent viloyati' => [
            'lat' => 41.054400, 'lng' => 69.642300, 'zoom' => 9,
            'districts' => [
                'Angren', 'Bekobod', 'Bo\'ka', 'Bo\'stonliq', 'Chinoz', 'Qibray',
                'Ohangaron', 'Oqqo\'rg\'on', 'Parkent', 'Piskent', 'Quyichirchiq',
                'O\'rtachirchiq', 'Yangiyo\'l', 'Yuqorichirchiq', 'Zangiota', 'Chirchiq', 'Nurafshon',
            ],
        ],
        'Andijon viloyati' => [
            'lat' => 40.783300, 'lng' => 72.350000, 'zoom' => 10,
            'districts' => [
                'Andijon', 'Asaka', 'Baliqchi', 'Bo\'z', 'Buloqboshi', 'Izboskan',
                'Jalaquduq', 'Xo\'jaobod', 'Qo\'rg\'ontepa', 'Marhamat', 'Oltinko\'l',
                'Paxtaobod', 'Shahrixon', 'Ulug\'nor',
            ],
        ],
        'Buxoro viloyati' => [
            'lat' => 39.768000, 'lng' => 64.421500, 'zoom' => 9,
            'districts' => [
                'Buxoro', 'Olot', 'G\'ijduvon', 'Jondor', 'Kogon', 'Qorako\'l',
                'Qorovulbozor', 'Peshku', 'Romitan', 'Shofirkon', 'Vobkent',
            ],
        ],
        'Farg\'ona viloyati' => [
            'lat' => 40.388600, 'lng' => 71.783300, 'zoom' => 9,
            'districts' => [
                'Farg\'ona', 'Marg\'ilon', 'Quvasoy', 'Qo\'qon', 'Beshariq', 'Bog\'dod',
                'Buvayda', 'Dang\'ara', 'Furqat', 'Toshloq', 'Uchko\'prik', 'Oltiariq',
                'Quva', 'Rishton', 'So\'x', 'O\'zbekiston', 'Yozyovon',
            ],
        ],
        'Jizzax viloyati' => [
            'lat' => 40.115800, 'lng' => 67.842200, 'zoom' => 9,
            'districts' => [
                'Jizzax', 'Arnasoy', 'Baxmal', 'Do\'stlik', 'Forish', 'G\'allaorol',
                'Mirzacho\'l', 'Paxtakor', 'Yangiobod', 'Zarbdor', 'Zafarobod', 'Zomin', 'Sharof Rashidov',
            ],
        ],
        'Xorazm viloyati' => [
            'lat' => 41.350000, 'lng' => 60.616700, 'zoom' => 9,
            'districts' => [
                'Urganch', 'Xiva', 'Bog\'ot', 'Gurlan', 'Hazorasp', 'Xonqa',
                'Qo\'shko\'pir', 'Shovot', 'Yangiariq', 'Yangibozor', 'Tuproqqal\'a',
            ],
        ],
        'Namangan viloyati' => [
            'lat' => 40.998300, 'lng' => 71.672600, 'zoom' => 9,
            'districts' => [
                'Namangan', 'Chortoq', 'Chust', 'Kosonsoy', 'Mingbuloq', 'Norin',
                'Pop', 'To\'raqo\'rg\'on', 'Uchqo\'rg\'on', 'Uychi', 'Yangiqo\'rg\'on',
            ],
        ],
        'Navoiy viloyati' => [
            'lat' => 40.103900, 'lng' => 65.373700, 'zoom' => 8,
            'districts' => [
                'Navoiy', 'Zarafshon', 'Karmana', 'Konimex', 'Qiziltepa', 'Xatirchi',
                'Navbahor', 'Nurota', 'Tomdi', 'Uchquduq',
            ],
        ],
        'Qashqadaryo viloyati' => [
            'lat' => 38.861400, 'lng' => 65.789100, 'zoom' => 9,
            'districts' => [
                'Qarshi', 'Shahrisabz', 'G\'uzor', 'Dehqonobod', 'Qamashi', 'Qasbi',
                'Kasbi', 'Kitob', 'Koson', 'Mirishkor', 'Muborak', 'Nishon',
                'Chiroqchi', 'Yakkabog\'',
            ],
        ],
        'Qoraqalpog\'iston Respublikasi' => [
            'lat' => 42.460200, 'lng' => 59.617300, 'zoom' => 7,
            'districts' => [
                'Nukus', 'Amudaryo', 'Beruniy', 'Bo\'zatov', 'Chimboy', 'Ellikqal\'a',
                'Kegeyli', 'Mo\'ynoq', 'Nukus tumani', 'Qanliko\'l', 'Qo\'ng\'irot',
                'Qorao\'zak', 'Shumanay', 'Taxtako\'pir', 'To\'rtko\'l', 'Xo\'jayli',
            ],
        ],
        'Samarqand viloyati' => [
            'lat' => 39.654200, 'lng' => 66.959700, 'zoom' => 9,
            'districts' => [
                'Samarqand', 'Kattaqo\'rg\'on', 'Bulung\'ur', 'Ishtixon', 'Jomboy',
                'Qo\'shrabot', 'Narpay', 'Nurobod', 'Oqdaryo', 'Pastdarg\'om',
                'Paxtachi', 'Payariq', 'Toyloq', 'Urgut', 'Tayloq',
            ],
        ],
        'Sirdaryo viloyati' => [
            'lat' => 40.488600, 'lng' => 68.785400, 'zoom' => 9,
            'districts' => [
                'Guliston', 'Yangiyer', 'Shirin', 'Boyovut', 'Guliston tumani', 'Mirzaobod',
                'Oqoltin', 'Sardoba', 'Sayxunobod', 'Sirdaryo', 'Xovos',
            ],
        ],
        'Surxondaryo viloyati' => [
            'lat' => 37.940000, 'lng' => 67.566700, 'zoom' => 9,
            'districts' => [
                'Termiz', 'Boysun', 'Denov', 'Jarqo\'rg\'on', 'Qiziriq', 'Qumqo\'rg\'on',
                'Muzrabot', 'Oltinsoy', 'Sariosiyo', 'Sherobod', 'Sho\'rchi',
                'Termiz tumani', 'Uzun', 'Angor', 'Bandixon',
            ],
        ],
    ];
}

/** Viloyat nomlari ro'yxati */
function uz_region_names(): array
{
    return array_keys(uz_regions());
}

/** Berilgan viloyatning markaz koordinatasi va tumanlari */
function uz_region_info(string $region): ?array
{
    return uz_regions()[$region] ?? null;
}
