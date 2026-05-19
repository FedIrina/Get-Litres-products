<?php
/*
 * Самостоятельный скрипт, который следует запускать напрямую из терминала или из bash-скрипта 
*/
ini_set('memory_limit','128M');

function litres_fail($message)
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$options = getopt('f:');
if (!isset($options['f']) || $options['f'] === '') {
    litres_fail('Укажите имя XML без расширения: php all_products2csv.php -f <имя>');
}

define('WP_USE_THEMES', false);
$path = str_replace('/wp-content/plugins/wc-litres-integration', '', __DIR__);

global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require_once($path.'/wp-load.php');
$xmlPath = __DIR__.'/xml/';
$csvPath = __DIR__.'/csv/';

foreach (array($xmlPath, $csvPath) as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        litres_fail("Не удалось создать каталог: $dir");
    }
}

$litresApiGetFileUrl = get_option('litresApiGetFileUrl');
$litresApiGetPdfFileUrl = get_option('litresApiGetPdfFileUrl');
$xmlFileName = $xmlPath. $options['f'].'.xml';
$csvFileName = $csvPath.'updated_'.$options['f'];
$removedFileName = $csvPath.'removed_'.$options['f'];
$csvFileCounter = 0;
$products2Remove = array();

$csv_header = array(
    'id',
    'external_id',
    'you_can_sell',
    'last_release',
    'updated',
    'size',
    'adult',
    'price',
    'regular_price',
    'sale_price',
    'cover',
    'file_parts',
    'wap_parts',
    'contract_ends',
    'type',
    'chars',
    'ISBN',
    'date_written_d',
    'images',
    'author',
    'genre',
    'sequence',
    'title',
    'subtitle',
    'description',
    'short_decription',
    'language',
    'src_language',
    'translator',
    'publisher',
    'file_group',
    'file_paths',
    'file_names',
    'format',
    'contract_author',
    'contract_title',
    'copyrights',
    'rating',
    'relations',
    'EAN',
    'fragment_link',
    'fragment_link_show',
    'duration',
    'pdf_pages',
    'reader',
);

$productType = array(
    '0' => 'текст',
    '1' => 'аудиокнига',
    '4' => 'PDF-книга',
    '11' => 'книга на английском языке (Adobe DRM protected)'
);

$languages = array(
    'ru' => 'Русский',
    'uk' => 'Украинский',
    'en' => 'Английский',
    'de' => 'Немецкий',
    'fr' => 'Французский',
    'ab' => 'Абхазский',
    'az' => 'Азербайджанский',
    'ay' => 'Аймара',
    'sq' => 'Албанский',
    'ar' => 'Арабский',
    'hy' => 'Армянский',
    'as' => 'Ассамский',
    'af' => 'Африкаанс',
    'ts' => 'Банту',
    'eu' => 'Баскский',
    'ba' => 'Башкирский',
    'be' => 'Белорусский',
    'bn' => 'Бенгальский',
    'my' => 'Бирманский',
    'bh' => 'Бихарский',
    'bg' => 'Болгарский',
    'br' => 'Бретонский',
    'cy' => 'Валлийский',
    'hu' => 'Венгерский',
    'wo' => 'Волоф',
    'vi' => 'Вьетнамский',
    'gd' => 'Гаэльский',
    'nl' => 'Голландский',
    'el' => 'Греческий',
    'ka' => 'Грузинский',
    'gn' => 'Гуарани',
    'da' => 'Датский',
    'gr' => 'Древнегреческий',
    'iw' => 'Древнееврейский',
    'dr' => 'Древнерусский',
    'zu' => 'Зулу',
    'he' => 'Иврит',
    'yi' => 'Идиш',
    'in' => 'Индонезийский',
    'ia' => 'Интерлингва',
    'ga' => 'Ирландский',
    'is' => 'Исландский',
    'es' => 'Испанский',
    'it' => 'Итальянский',
    'kk' => 'Казахский',
    'kn' => 'Каннада',
    'ca' => 'Каталанский',
    'ks' => 'Кашмири',
    'qu' => 'Кечуа',
    'ky' => 'Киргизский',
    'zh' => 'Китайский',
    'ko' => 'Корейский',
    'kw' => 'Корнский',
    'co' => 'Корсиканский',
    'ku' => 'Курдский',
    'km' => 'Кхмерский',
    'xh' => 'Кхоса',
    'la' => 'Латинский',
    'lv' => 'Латышский',
    'lt' => 'Литовский',
    'mk' => 'Македонский',
    'mg' => 'Малагасийский',
    'ms' => 'Малайский',
    'mt' => 'Мальтийский',
    'mi' => 'Маори',
    'mr' => 'Маратхи',
    'mo' => 'Молдавский',
    'mn' => 'Монгольский',
    'na' => 'Науру',
    'ne' => 'Непали',
    'no' => 'Норвежский',
    'pa' => 'Панджаби',
    'fa' => 'Персидский',
    'pl' => 'Польский',
    'pt' => 'Португальский',
    'ps' => 'Пушту',
    'rm' => 'Ретороманский',
    'ro' => 'Румынский',
    'rn' => 'Рунди',
    'sm' => 'Самоанский',
    'sa' => 'Санскрит',
    'sr' => 'Сербский',
    'si' => 'Сингальский',
    'sd' => 'Синдхи',
    'sk' => 'Словацкий',
    'sl' => 'Словенский',
    'so' => 'Сомали',
    'st' => 'Сото',
    'sw' => 'Суахили',
    'su' => 'Сунданский',
    'tl' => 'Тагальский',
    'tg' => 'Таджикский',
    'th' => 'Тайский',
    'ta' => 'Тамильский',
    'tt' => 'Татарский',
    'te' => 'Телугу',
    'bo' => 'Тибетский',
    'tr' => 'Турецкий',
    'tk' => 'Туркменский',
    'uz' => 'Узбекский',
    'ug' => 'Уйгурский',
    'ur' => 'Урду',
    'fo' => 'Фарерский',
    'fj' => 'Фиджи',
    'fi' => 'Финский',
    'fy' => 'Фризский',
    'ha' => 'Хауса',
    'hi' => 'Хинди',
    'hr' => 'Хорватскосербский',
    'cs' => 'Чешский',
    'sv' => 'Шведский',
    'sn' => 'Шона',
    'eo' => 'Эсперанто',
    'et' => 'Эстонский',
    'jv' => 'Яванский',
    'ja' => 'Японский'
);

$relations = array(
    '' => '',
    '5' => 'cборник',
    '6' => 'часть',
    '7' => 'переиздание',
    '8' => 'другой носитель'
);

$productVolume = array(
);

// В будущем вынести редактирование массива неиспользуемых жанров в настройки в админке
$LitresCategories  = array();
$notUsedCategories = array(
    'альманахи',
    'сборники',
    'подкасты',
    'ГИА по химии (ОГЭ, ГВЭ)',
    'ГИА по физике (ОГЭ, ГВЭ)',
    'ГИА по русскому языку (ОГЭ, ГВЭ)',
    'ГИА по обществознанию (ОГЭ, ГВЭ)',
    'ГИА по математике (ОГЭ, ГВЭ)',
    'ГИА по литературе (ОГЭ, ГВЭ)',
    'ГИА по истории (ОГЭ, ГВЭ)',
    'ГИА по информатике и ИКТ (ОГЭ, ГВЭ)',
    'ГИА по иностранному языку (ОГЭ, ГВЭ)',
    'ГИА по географии (ОГЭ, ГВЭ)',
    'ГИА по биологии (ОГЭ, ГВЭ)',
    'ЕГЭ по биологии',
    'ЕГЭ по географии',
    'ЕГЭ по иностранному языку',
    'ЕГЭ по информатике и ИКТ',
    'ЕГЭ по истории',
    'ЕГЭ по литературе',
    'ЕГЭ по математике',
    'ЕГЭ по обществознанию',
    'ЕГЭ по русскому языку',
    'ЕГЭ по физике',
    'ЕГЭ по химии',

);

$notUsedPublishers = array(
    'SelfPub'
);

// ID категории WooCommerce (product_cat) для товаров без жанра в фиде Литрес
define('DEFAULT_PRODUCT_CAT_ID', 15);

$priceLimit = 101;

$csvArray = array();

$xml_reader = new XMLReader;

if (!$xml_reader->open($xmlFileName)) {
    litres_fail("Не удалось открыть файл: $xmlFileName");
}

// Переходим к первому узлу <product>
while ($xml_reader->read() && $xml_reader->name !== 'updated-book');

while ($xml_reader->name === 'updated-book') {
    $item = simplexml_load_string($xml_reader->readOuterXML(),null,LIBXML_NOCDATA);
    if (!$item) {
        $xml_reader->next('updated-book');
        continue; 
    }
    // Пропускаем книги, запрещенные Литрес к продаже
    if ( (string)$item->attributes()->you_can_sell != "1") {
        $xml_reader->next('updated-book');
        continue;  
    }
    // Пропускаем книги нежелательных издательств
     if (in_array((string)$item->attributes()->publisher, $notUsedPublishers)){
        $xml_reader->next('updated-book');
        continue;
    }
    // Пропускаем книги с низкой ценой
    if ((string)$item->attributes()->price < $priceLimit){
        $xml_reader->next('updated-book');
        continue;
    }
    // Пропускаем книги, у которых провообладатель - автор
    $copyrights = array();
    if ($item->copyrights->copyright instanceof SimpleXMLElement) {
        foreach ($item->copyrights->copyright as $elem) {
            $copyright = array();
            foreach ($elem->attributes() as $id => $value) {
                if ($id == 'title') {
                    $copyright[] = (string)$value;
                }
            }
            $copyrights[] = implode(',', $copyright);
        }
    }

    $readersArr = array();

    foreach ($item->{"title-info"}->reader as $reader) {
        $readersArr[] = (string)$reader->nickname;
    }

    // Пропускаем книги без авторов
    $authorsArr = array();
    $authorSet = (isset($item->{"title-info"}->authors))?$item->{"title-info"}->authors:$item->{"title-info"}->author;
    foreach ($authorSet as $author) {
        $authorsArr[] = (string)$author->{"first-name"}.' '.(string)$author->{"last-name"};
    }


    if (!count($authorsArr)) {
        $xml_reader->next('updated-book');
        continue; 
    }

    $translatorArr = array();
    foreach ($item->{"title-info"}->translator as $translator) {
        $fioArr = array();
        if (isset($translator->{"first-name"})) {
            $fioArr[] = (string)$translator->{"first-name"};
        }
        if (isset($translator->{"middle-name"})) {
            $fioArr[] = (string)$translator->{"middle-name"};
        }
        if (isset($translator->{"last-name"})) {
            $fioArr[] = (string)$translator->{"last-name"};
        }
        $translatorArr[] = implode(' ', $fioArr);
    }
    $genresArr = array();
    foreach ($item->genres->genre as $genre) {

        //Пропускаем нежелательные товарные категории
        if ( in_array( (string)$genre->attributes()->title, $notUsedCategories ) ) {
            $xml_reader->next('updated-book');
            continue;
        }
        $genresArr[] = (string)$genre->attributes()->title;
    }
    $genresArr = terms2set($genresArr);

    $annotation = '';
    if ($item->annotation && $item->annotation instanceof SimpleXMLElement) {
        if ( $item->annotation->children() ) {
            foreach( $item->annotation->children() as $child ) {
                $annotation .= $child->saveXML();
            }
        } else {
            $annotation = $item->annotation->saveXML();
        }
    }

    $ean = str_replace('-','',(string)$item->attributes()->isbn);
    if ( (string)$item->attributes()->type !=="1" && !(string)$item->attributes()->isbn ) {
        // Пропускаем книги без ISBN
        $xml_reader->next('updated-book');
        continue;
    }

    if (!$ean) {
        $ean = 'нет';
    }

    // Данные для скачивания файлов ознакомительного фрагмента и полного текста издания
    $filesArr = array();
    $fileNames = array();
    $fileFormat = array();
    $fileVolume = array();
    //Объем произведения в символах, секундах или количестве страниц
    $duration = '';
    $pdf_pages = '';
    switch ((string)$item->attributes()->type)
    {
        case '0': //Электронные тексты
            $fragment_link =  htmlentities('https://partnersdnld.litres.ru/pub/t/'.(string)$item->attributes()->id.'.epub');
            $fragment_link_show = 'yes';
            $getFileUrl = $litresApiGetFileUrl;
            foreach ($item->files->file as $file) {
                $file_type = (string)$file->attributes()->type;
                if ($file_type == 'epub' || $file_type == 'a4.pdf' || $file_type == 'a6.pdf' ) {
                    $filesArr[] = (string)$file->attributes()->type.' ('.(string)$file->attributes()->size.' байт)';
                    $fileFormat[] = (string)$file->attributes()->type;
                    $fileVolume[] = ceil(((int)$file->attributes()->size)/1024);
                }
            }
            $fileNames = $filesArr;
            break;
        case '1': //Аудио
            $fragment_link =  htmlentities('https://partnersdnld.litres.ru/get_mp3_trial/'.(string)$item->attributes()->id.'.mp3');
            $fragment_link_show = 'no';
            $getFileUrl = $litresApiGetFileUrl;
            foreach ($item->files->children() as $group) {
                $group_name = $group->attributes()->value; //Общее имя для группы файлов
                foreach ($group->children() as $file) {
                    if (strpos($group_name, 'Ознакомительный') !== false || strpos($group_name, 'Стандартное') !== false) continue;
                   $filesArr[] = $group_name.'@/'.(string)$file->attributes()->id.'/'.(string)$file->attributes()->filename;
                   if (!in_array((string)$file->attributes()->mime_type, $fileFormat, true)) {
                    $fileFormat[] = (string)$file->attributes()->mime_type;
                   }
                   $fileNames[] = $group_name;
                }
           }
            $duration = format_time((string)$item->attributes()->chars);
            break;
        case '4': //pdf
            $fragment_link =  htmlentities('https://partnersdnld.litres.ru/get_pdf_trial/'.(string)$item->attributes()->id.'.pdf');
            $fragment_link_show = 'yes';
            $getFileUrl = $litresApiGetPdfFileUrl;
            foreach ($item->files->children() as $group) {
                $group_name = $group->attributes()->value; //Общее имя для группы файлов
                foreach ($group->children() as $file) {
                    if (strpos($group_name, 'Ознакомительный') !== false) continue;
                   $filesArr[] = $group_name.'@/'.(string)$file->attributes()->id.'/'.(string)$file->attributes()->filename;
                   if (!in_array((string)$file->attributes()->mime_type, $fileFormat, true)) {
                    $fileFormat[] = 'pdf';
                   }
                   $fileNames[] = $group_name.' ('.(string)$file->attributes()->size.' байт)';
                }
            }

            $pdf_pages = (string)$item->attributes()->chars;
            break;
        default:
            $fragment_link = '';
    }

    $csvArray[] = array(
        'id' => (string)$item->attributes()->id, // уникальный ID книги в системе
        'external_id' => (string)$item->attributes()->external_id, // уникален в рамках одной сущности (например, может быть книга с ID=12345, а может быть автор с ID=12345)
        'you_can_sell' => ((string)$item->attributes()->you_can_sell=="1")?'publish':'draft', // если 0, немедленно снять книгу с продажи, но карточка тоара остается
        'last_release' => (string)$item->attributes()->last_release, // время размещения последней версии файла
        'updated' => (string)$item->attributes()->updated, // время последнего «движения» по книге. Меняется при обновлении текста, обложки, прав на продажу, смене цены и т.п.
        'size' => implode(', ', $fileVolume), // размер ZIP-файла с книгой (в байтах)
        'adult' => (string)$item->attributes()->adult,
        'price'=> (string)$item->attributes()->price,
        'regular_price'=>'',
        'sale_price' => '',
        'cover' =>(string)$item->attributes()->cover, // Если строка не пустая' => 'то она указывает на формат оригинальной обложки (jpg или png)
        'file_parts' => (string)$item->attributes()->file_parts, // количество фрагментов при онлайн-чтении (целочисленное значение)
        'wap_parts' => (string)$item->attributes()->wap_parts,
        'contract_ends' => (string)$item->attributes()->contract_ends,
        'type' => (string)$item->attributes()->type,
        'chars' => (string)$item->attributes()->chars,
        'ISBN' => (string)$item->attributes()->isbn,
        'date_written_d' => date("d.m.Y", strtotime((string)$item->attributes()->date_written_d)),
        'images' => (string)$item->attributes()->images,
        'author' => implode(', ', $authorsArr),
        'genre' => implode(', ', $genresArr),
        'sequence' => implode(', ', getSequence($item->sequences)),
        'title' => (string)$item->{"book-title"}->attributes()->title,
        'subtitle' => (string)$item->{"book-title"}->attributes()->subtitle,
        'description' => $annotation,
        'short_decription'=>'',
        'language' => ((string)$item->attributes()->lang)?$languages[(string)$item->attributes()->lang]:'',
        'src_language' => ((string)$item->attributes()->src_lang)?$languages[(string)$item->attributes()->src_lang]:'',
        'translator' => implode(', ', $translatorArr),
        'publisher' => (string)$item->attributes()->publisher,
        'file_group' => '',
        'file_paths' => file_paths($getFileUrl, (string)$item->attributes()->external_id, (string)$item->attributes()->type, $filesArr),
        'file_names' => implode(', ',$fileNames),
        'format'    => implode(', ', $fileFormat),
        'contract_author' => (string)$item->attributes()->contract_author,
        'contract_title' => (string)$item->attributes()->contract_title,
        'copyrights' => implode( '; ', $copyrights ),
        'rating' => (string)$item->attributes()->rating,
        'relations' => $relations[(string)$item->attributes()->relations],
        'EAN' => $ean,
        'fragment_link' => $fragment_link,
        'fragment_link_show' => $fragment_link_show,
        'duration' => $duration,
        'pdf_pages' => $pdf_pages,
        'reader' => implode(', ', $readersArr)
        );
    //Сбрасываем в csv файл каждые 3000 элементов updated-book
    if (count($csvArray) == 3000) {
        $csvFileCounter=$csvFileCounter+1;
        makeCsv($csv_header, $csvArray, $csvFileName.'_'.$csvFileCounter.'.csv');
        $csvArray = array();
    }
    $xml_reader->next('updated-book');
}

// Сбрасываем в csv последний блок, если в нем меньше 3000 строк
if (count($csvArray) > 0) {
    $csvFileCounter=$csvFileCounter+1;
    makeCsv($csv_header, $csvArray, $csvFileName.'_'.$csvFileCounter.'.csv');
    $csvArray = array();
}
$xml_reader->close();

$csvFileCounter = 0;
$xml_reader = new XMLReader;
if (!$xml_reader->open($xmlFileName)) {
    litres_fail("Не удалось открыть файл: $xmlFileName");
}
while ($xml_reader->read() && $xml_reader->name !== 'removed-book');

while ($xml_reader->name === 'removed-book') {
    $item = simplexml_load_string($xml_reader->readOuterXML(),null,LIBXML_NOCDATA);
    if ((string)$item->attributes()->s_uid_new) {
        // Здесь будет формирование списка на замену одних товаров другими (еще не придумана)
    }
    $products2Remove[] = array(
        'id' => (string)$item->attributes()->id
    );
    if (count($products2Remove) == 3000) {
        $csvFileCounter = $csvFileCounter + 1;
        makeCsv(array('id'), $products2Remove, $removedFileName.'_'.$csvFileCounter.'.csv');
        $products2Remove = array();
    }
    $xml_reader->next('removed-book');
}

// Сбрасываем в csv последний блок, если в нем меньше 3000 строк
if (count($products2Remove) > 0) {
    $csvFileCounter = $csvFileCounter + 1;
    makeCsv(array('id'), $products2Remove, $removedFileName.'_'.$csvFileCounter.'.csv');
}

$xml_reader->close();

function getSequence($sequences) 
{
    if (!$sequences) {
        return array();
    }
    $sequencesArr = array();
    foreach ($sequences->sequence as $sequence) {
            $sequencesArr[] = (string)$sequence->attributes()->name;
    }
    return $sequencesArr;
}

function makeCsv($csv_header, $product_array, $csvFileName )
{
    $fp = fopen($csvFileName, 'c');
    if ($fp === false) {
        litres_fail("Не удалось записать CSV: $csvFileName");
    }
        fputcsv($fp, $csv_header, ';', '"');
        foreach ($product_array as $key => $litres_product) {
            $csv_string = array();
            foreach( $csv_header as $field_name) {
                $csv_string[] = $litres_product[$field_name];
            }
            fputcsv($fp, $csv_string, ';', '"');
        }
        fclose($fp);
}

function terms2set($genresArr){
    if (!count($genresArr)) {
        $terms = array(DEFAULT_PRODUCT_CAT_ID);
    } else {
        $terms = array();
    }

	foreach( $genresArr as $genre ) {
		$args = [
			'taxonomy'     	=> 'product_cat',
			'name' 	 => trim($genre),
			'fields'				=> 'ids',
			'hide_empty'		=> false,
            'meta_query' => array(
                array(
                  'key' => 'litres_id',
                  'compare' => 'EXISTS' // Прикрепляем товар только к категория Литрес
                )
              )
		];
		$result = array_merge( $terms, get_terms($args) );
		$terms = $result;
	}
	return $terms;
}

function file_paths($getFileUrl, $bookId, $type, $filesArray){
	if (!$filesArray) {
		return '';
	}
	$filePathArr = array();
	foreach ($filesArray as $file) {
        if ($type == 1 || $type == 4) {
            $fileExt = trim( explode("@", $file)[1]);
        } else {
		    $fileExt = '.'.trim( explode(" (", $file)[0]);
        }
		$filePathArr[] = $getFileUrl.'t/u/'.urlencode(strtolower($bookId)).$fileExt;
	}
	return implode(',', $filePathArr);
}

function format_time($t,$f=':') // t = seconds, f = separator 
{
  return sprintf("%02d%s%02d%s%02d%s", floor($t/3600), ' ч. ', ($t/60)%60, ' мин. ', $t%60, ' сек.');
}
