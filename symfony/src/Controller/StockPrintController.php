<?php
// src/Controller/StockPrintController.php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security as SecurityCore;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Stock;
use App\Entity\StockMaterials;
use App\Repository\ProvidersRepository;
use App\Repository\StockRepository;
use App\Repository\StockMaterialsRepository;
use App\Repository\StockStockMaterialsRepository;
use App\Repository\StockApplicationsMaterialsRepository;
use App\Repository\UnitsRepository;

class StockPrintController extends AbstractController
{
    private $security;
    private $entityManager;

    public function __construct(SecurityCore $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/stock/print/pm", methods={"GET"})
     * @Security("is_granted('ROLE_STOCK') or is_granted('ROLE_BUH')")
     */
    public function printPM(
        Request $request,
        ProvidersRepository $providersRepository,
        StockRepository $stockRepository,
        StockMaterialsRepository $stockMaterialsRepository,
        StockStockMaterialsRepository $stockStockMaterialsRepository
    ): Response
    {
        $id = $request->query->get('number');
        if ($id === null || empty($id) || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Проверяем наличие документа
        $objStock = $stockRepository->findBy( array('id' => (int)$id, 'doctype' => 0) );
        if (sizeof($objStock) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($objStock)) {$objStock = array_shift($objStock);}

        //Получаем материалы
        $objSSMaterials = $stockStockMaterialsRepository->findBy( array('stock' => (int)$id) );
        $objMaterials = [];
        foreach ($objSSMaterials as $objSSMaterial) {
            $tmp = new \stdClass;
            $tmp->obj = $objSSMaterial->getStockMaterial();
            $tmp->count = $objSSMaterial->getCount();
            $tmp->id = $objSSMaterial->getId();
            $objMaterials[] = $tmp;
        }

        //Получаем поставщика
        $objProvider = $providersRepository->findBy( array('inn' => (int)$objStock->getProvider()) );
        if (sizeof($objProvider) > 0) {
            if (is_array($objProvider)) {$objProvider = array_shift($objProvider);}
            $params['provider'] = $objProvider;
        }

        //Печатаем
        ob_start();

        if (!empty($objStock->getNote())) {
            echo '<div class="block" style="text-align: left; color: #777;">'.$objStock->getNote().'</div>'."\n";
        }
        
        echo <<<HERE
    <div class="block" style="text-align: right;">Типовая межотраслевая форма № М-4<br />Утверждена постановление Госкомстата России от 30.10.97 № 71а</div>
HERE;
        echo "<div class=\"block\" style=\"text-align: center;\"><h1>ПРИХОДНЫЙ ОРДЕР № ".$objStock->getId()."</h1></div>\n";
        echo <<<HERE
    <div class="block">
        <table>
            <tr>
                <td colspan="3">&nbsp;</td>
                <td class="bordered">Коды</td>
            </tr>
            <tr>
                <td colspan="2">&nbsp;</td>
                <td style="text-align: right;">Форма по ОКУД</td>
                <td class="bordered" style="border-left-width: 2px; border-right-width: 2px; border-top-width: 2px;">0315003</td>
            </tr>
            <tr>
                <td style="text-align: left; width: 160px;">Организация:</td>
                <td style="border-bottom: 1px solid #000; text-align: left;">ЗАО «Артель старателей «Витим»</td>
                <td style="text-align: right; width: 100px;">по ОКПО</td>
                <td class="bordered" style="border-left-width: 2px; border-bottom-width: 2px; border-right-width: 2px; width: 150px;">33288542</td>
            </tr>
            <tr>
                <td style="text-align: left;">Структурное подразделение:</td>
                <td colspan="3" style="border-bottom: 1px solid #000; text-align: left;">База Иркутск</td>
            </tr>
        </table>
    </div>
    <div class="block" style="margin-top: 20px;">
        <table>
            <tr>
                <td rowspan="2" class="bordered">Дата составления</td>
                <td rowspan="2" class="bordered">Код вида операции</td>
                <td rowspan="2" class="bordered">Склад</td>
                <td colspan="2" class="bordered">Поставщик</td>
                <td rowspan="2" class="bordered">Страховая компания</td>
                <td colspan="2" class="bordered">Корреспондирующий счет</td>
                <td colspan="2" class="bordered">Номер документа</td>
            </tr>
            <tr>
                <td class="bordered">наименование</td>
                <td class="bordered">код</td>
                <td class="bordered">счет, субсчет</td>
                <td class="bordered">код аналитического учета</td>
                <td class="bordered">сопроводительного</td>
                <td class="bordered">платежного</td>
            </tr>
            <tr style="border: 2px solid #000;">
HERE;
    
        echo "            <td class=\"bordered\">".$objStock->getDate()->format('d.m.Y')."</td>\n";
        echo "            <td class=\"bordered\"></td>\n";
        echo "            <td class=\"bordered\">База Иркутск</td>\n";
    
        if ($objStock->getProvider() == 0) {
            echo "            <td class=\"bordered\" colspan=\"2\">Наличный расчет</td>\n";
        } else {        
            echo "            <td class=\"bordered\">".$objProvider->getTitle()."</td>\n";
            echo "            <td class=\"bordered\">".$objProvider->getInn()."</td>\n";
        }
    
        echo <<<HERE
                <td class="bordered"></td>
                <td class="bordered">60.1</td>
                <td class="bordered"></td>
                <td class="bordered"></td>
                <td class="bordered"></td>
            </tr>
        </table>
    </div>
    <div class="block" style="margin-top: 20px;">
        <table>
            <tr>
                <td colspan="2" class="bordered">Материальные ценности</td>
                <td colspan="2" class="bordered">Единица измерения</td>
                <td colspan="2" class="bordered">Количество</td>
                <td rowspan="2" class="bordered">Цена, руб. коп.</td>
                <td rowspan="2" class="bordered">Сумма без учета НДС, руб. коп.</td>
                <td rowspan="2" class="bordered">Сумма НДС, руб. коп.</td>
                <td rowspan="2" class="bordered">Всего с учетом НДС, руб. коп.</td>
                <td rowspan="2" class="bordered">Номер паспорта</td>
                <td rowspan="2" class="bordered">Порядковый номер по складской картотеке</td>
            </tr>
            <tr>
                <td class="bordered">наименование, сорт, марка, размер</td>
                <td class="bordered">номенклатурный номер</td>
                <td class="bordered">код</td>
                <td class="bordered">наименование</td>
                <td class="bordered">по документу</td>
                <td class="bordered">принято</td>
            </tr>
            <tr>
                <td class="bordered">1</td>
                <td class="bordered" style="border-bottom-width: 2px;">2</td>
                <td class="bordered" style="border-bottom-width: 2px;">3</td>
                <td class="bordered">4</td>
                <td class="bordered">5</td>
                <td class="bordered" style="border-bottom-width: 2px;">6</td>
                <td class="bordered" style="border-bottom-width: 2px;">7</td>
                <td class="bordered" style="border-bottom-width: 2px;">8</td>
                <td class="bordered" style="border-bottom-width: 2px;">9</td>
                <td class="bordered" style="border-bottom-width: 2px;">10</td>
                <td class="bordered">11</td>
                <td class="bordered">12</td>
            </tr>
HERE;
    
        $sCount = 0; $sSum = 0; $sTax = 0; $sTotal = 0;
        foreach ($objMaterials as $material) {
            echo "        <tr>\n";
            echo "            <td class=\"bordered\" style=\"text-align: left;\">".$material->obj->getTitle()."</td>\n";
    
            $mid = $material->obj->getId(); while (strlen($mid) < 6) {$mid = '0'.$mid;}
    
            echo "            <td class=\"bordered\" style=\"border-left-width: 2px;\">".$mid."</td>\n";
    
            $uid = $material->obj->getUnit()->getId(); while (strlen($uid) < 6) {$uid = '0'.$uid;}
    
            echo "            <td class=\"bordered\" style=\"border-right-width: 2px;\">".$uid."</td>\n";
            echo "            <td class=\"bordered\">".$material->obj->getUnit()->getTitle()."</td>\n";
            echo "            <td class=\"bordered\"></td>\n";
            echo "            <td class=\"bordered\" style=\"border-left-width: 2px;\">".number_format($material->count, 2, ".", "")."</td>\n";
            echo "            <td class=\"bordered\">".number_format($material->obj->getPrice(), 2, ".", " ")."</td>\n";
            echo "            <td class=\"bordered\">".number_format($material->obj->getSum(), 2, ".", " ")."</td>\n";
            echo "            <td class=\"bordered\">".number_format($material->obj->getTax(), 2, ".", " ")."</td>\n";
            echo "            <td class=\"bordered\" style=\"border-right-width: 2px;\">".number_format($material->obj->getTotal(), 2, ".", " ")."</td>\n";
            echo "            <td class=\"bordered\"></td>\n";
            echo "            <td class=\"bordered\"></td>\n";
            echo "        </tr>\n";

            $sCount += $material->count; 
            $sSum += $material->obj->getSum(); 
            $sTax += $material->obj->getTax(); 
            $sTotal += $material->obj->getTotal();
        }
    
        echo <<<HERE
            <tr>
                <td></td>
                <td colspan="2" style="border-top: 2px solid #000;">&nbsp;</td>
                <td></td>
                <td style="text-align: right;">Итого</td>
HERE;
        
        echo "            <td class=\"bordered\" style=\"border-left-width: 2px;\">".number_format($sCount, 2, ".", "")."</td>\n";
        echo "            <td class=\"bordered\">Х</td>\n";
        echo "            <td class=\"bordered\">".number_format($sSum, 2, ".", " ")."</td>\n";
        echo "            <td class=\"bordered\">".number_format($sTax, 2, ".", " ")."</td>\n";
        echo "            <td class=\"bordered\" style=\"border-right-width: 2px;\">".number_format($sTotal, 2, ".", " ")."</td>\n";
    
        echo <<<HERE
                <td colspan="2"></td>
            </tr>
        </table>
    </div>

    <div class="block">
HERE;
        echo '        <img src="'.getcwd().'/img/signatures/signature_0.png" alt="" style="height: 75px; position: absolute; margin-left: 210px; margin-bottom: -40px; width: 180px;" />'."\n";
        echo <<<HERE
        <table>
            <tr>
                <td style="width: 70px; text-align: right;">Принял</td>
                <td style="border-bottom: 1px solid #000; width: 13%;">зав. базой</td>
                <td style="width: 5px;"></td>
                <td style="border-bottom: 1px solid #000; width: 13%;"></td>
                <td style="width: 5px;"></td>
                <td style="border-bottom: 1px solid #000; width: 13%;">Ю.Н. Жарков</td>
                <td></td>
                <td style="width: 70px; text-align: right;">Сдал</td>
                <td style="border-bottom: 1px solid #000; width: 13%;"></td>
                <td style="width: 5px;"></td>
                <td style="border-bottom: 1px solid #000; width: 13%;"></td>
                <td style="width: 5px;"></td>
                <td style="border-bottom: 1px solid #000; width: 13%;"></td>
            </tr>
            <tr>
                <td></td>
                <td style="font-size: 0.9em;">должность</td>
                <td></td>
                <td style="font-size: 0.9em;">подпись</td>
                <td></td>
                <td style="font-size: 0.9em;">расшифровка подписи</td>
                <td></td>
                <td></td>
                <td style="font-size: 0.9em;">должность</td>
                <td></td>
                <td style="font-size: 0.9em;">подпись</td>
                <td></td>
                <td style="font-size: 0.9em;">расшифровка подписи</td>
            </tr>
        </table>
    </div>
HERE;

        $content = ob_get_contents();
        ob_end_clean();

        $css = file_get_contents('css/pdf.css');

        require_once $this->getParameter('kernel.project_dir').'/../vendor/autoload.php';

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'orientation' => 'L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 7,
            'margin_bottom' => 7,
            'margin_header' => 10,
            'margin_footer' => 10,
            'default_font_size' => 8,

            'fontDir' => array_merge($fontDirs, ['fonts']),
            'fontdata' => $fontData + [
                'tahoma' => [
                    'R' => 'tahoma.ttf',
                    'B' => 'tahoma-bold.ttf',
                ],
            ],
            'default_font' => 'tahoma'
        ]);
        $mpdf->debug = true;

        $mpdf->keep_table_proportions = TRUE;
        $mpdf->shrink_tables_to_fit=1;
        $mpdf->SetTitle('Просмотр приходного ордера'.$objStock->getId());
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($content, \Mpdf\HTMLParserMode::HTML_BODY);
        
        $mpdf->Output('Приходный ордер'.$objStock->getId().'.pdf', 'I');
    }

    /**
     * @Route("/stock/print/tn", methods={"GET"})
     * @Security("is_granted('ROLE_STOCK') or is_granted('ROLE_BUH')")
     */
    public function printTN(
        Request $request,
        ProvidersRepository $providersRepository,
        StockRepository $stockRepository,
        StockMaterialsRepository $stockMaterialsRepository,
        StockStockMaterialsRepository $stockStockMaterialsRepository,
        StockApplicationsMaterialsRepository $stockApplicationsMaterialsRepository
    ): Response
    {
        $id = $request->query->get('number');
        if ($id === null || empty($id) || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Проверяем наличие документа
        $objStock = $stockRepository->findBy( array('id' => (int)$id, 'doctype' => 1) );
        if (sizeof($objStock) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($objStock)) {$objStock = array_shift($objStock);}

        //Получаем материалы
        $objSSMaterials = $stockStockMaterialsRepository->findBy( array('stock' => (int)$id) );
        $objMaterials = [];
        foreach ($objSSMaterials as $objSSMaterial) {
            $tmp = new \stdClass;
            $tmp->obj = $objSSMaterial->getStockMaterial();
            $tmp->count = $objSSMaterial->getCount();
            $tmp->id = $objSSMaterial->getId();
            $objMaterials[] = $tmp;
        }

        //Получаем заявки
        $SAP = $stockApplicationsMaterialsRepository->findBy( array('stock' => $id) );
        $applications = [];
        foreach ($SAP as $SAP_row) {
            $objApplication = $SAP_row->getMaterial()->getMaterial()->getApplication();
            $exist = false;
            foreach ($applications as $application) {
                if ($objApplication->getId() == $application->getId()) {
                    $exist = true; break;
                }
            }

            if (!$exist) {
                $applications[] = $objApplication;
            }
        }

        //Проверяем наличие документа
        $objStockParent = $stockRepository->findBy( array('id' => (int)$objStock->getParent()) );
        if (is_array($objStockParent)) {$objStockParent = array_shift($objStockParent);}

        //Получаем поставщика
        $objProvider = $providersRepository->findBy( array('inn' => (int)$objStockParent->getProvider()) );
        if (sizeof($objProvider) > 0) {
            if (is_array($objProvider)) {$objProvider = array_shift($objProvider);}
        }

        //Печатаем
        ob_start();

        echo '<div class="block" style="float: right; text-align: right;">Типовая межотраслевая форма № М-11<br />Утверждена постановление Госкомстата России от 30.10.97 № 71а</div>'."\n";

        if (sizeof($applications) > 0) {
            echo '<div class="block" style="color: #777; float: left;">'."\n";

            for ($i=0; $i<sizeof($applications); $i++) {
                if ($i > 0) {echo '<br />';}
                echo 'Заявка №'.$applications[$i]->getId().' от '.$applications[$i]->getDateCreate()->format('d.m.Y').' ('.$applications[$i]->getAuthor()->getShortUsername().')'."\n";
            }

            echo '</div>'."\n";
        }

        echo '<div style="clear: both;"></div>'."\n";
        
        $num = $objStock->getId(); while (strlen($num) < 6) {$num = '0'.$num;}
        echo "<div class=\"block\" style=\"text-align: center;\"><h1>ТРЕБОВАНИЕ-НАКЛАДНАЯ № ".$num."</h1></div>\n";
        echo <<<HERE
        <div class="block">
            <table>
                <tr>
                    <td colspan="3">&nbsp;</td>
                    <td class="bordered">Коды</td>
                </tr>
                <tr>
                    <td colspan="2">&nbsp;</td>
                    <td style="text-align: right;">Форма по ОКУД</td>
                    <td class="bordered" style="border-left-width: 2px; border-right-width: 2px; border-top-width: 2px;">0315006</td>
                </tr>
                <tr>
                    <td style="text-align: left; width: 60px;">Организация:</td>
                    <td style="border-bottom: 1px solid #000; text-align: left;">ЗАО «Артель старателей «Витим»</td>
                    <td style="text-align: right; width: 100px;">по ОКПО</td>
                    <td class="bordered" style="border-left-width: 2px; border-bottom-width: 2px; border-right-width: 2px; width: 150px;">33288542</td>
                </tr>
            </table>
        </div>
        <div class="block" style="margin-top: 20px;">
            <table>
                <tr>
                    <td rowspan="2" class="bordered">Дата составления</td>
                    <td rowspan="2" class="bordered">Код вида операции</td>
                    <td colspan="2" class="bordered">Отправитель</td>
                    <td colspan="2" class="bordered">Получатель</td>
                    <td colspan="2" class="bordered">Корреспондирующий счет</td>
                    <td rowspan="2" class="bordered">Учетная единица выпуска продукции (работ, услуг)</td>
                </tr>
                <tr>
                    <td class="bordered">структурное подразделение</td>
                    <td class="bordered">вид деятельности</td>
                    <td class="bordered">структурное подразделение</td>
                    <td class="bordered">вид деятельности</td>
                    <td class="bordered">счет, субсчет</td>
                    <td class="bordered">код аналитического учета</td>
                </tr>
                <tr style="border: 2px solid #000;">
HERE;
        
        echo "            <td class=\"bordered\">".$objStock->getDate()->format('d.m.Y')."</td>\n";
        echo "            <td class=\"bordered\"></td>\n";
        echo "            <td class=\"bordered\">База Иркутск</td>\n";
        echo "            <td class=\"bordered\"></td>\n";
        echo "            <td class=\"bordered\">".$objStock->getOffice()->getTitle()."</td>\n";
        echo <<<HERE
                    <td class="bordered"></td>
                    <td class="bordered"></td>
                    <td class="bordered"></td>
                    <td class="bordered"></td>
                </tr>
            </table>
        </div>
        <div class="block" style="margin-top: 10px;">
            <table>
                <tr>
                    <td style="text-align: left; width: 70px;">Через кого:</td>
HERE;
        
        echo "            <td style=\"border-bottom: 1px solid #000; text-align: left;\">".$objStock->getWay()."</td>\n";
    
        echo <<<HERE
                </tr>
                <tr>
HERE;
        
        $pmid = $objStock->getParent(); while (strlen($pmid) < 6) {$pmid = '0'.$pmid;}
    
        echo "            <td colspan=\"2\" style=\"padding-top: 10px; text-align: left;\">Списано на основании приходного ордера № ".$pmid." (".$objProvider->getTitle().")</td>\n";
    
        echo <<<HERE
                </tr>
            </table>
        </div>
        <div class="block" style="margin-top: 10px;">
            <table>
                <tr>
                    <td colspan="1" class="bordered">Корреспондирующий счет</td>
                    <td colspan="2" class="bordered">Материальные ценности</td>
                    <td colspan="2" class="bordered">Единица изменения</td>
                    <td colspan="2" class="bordered">Количество</td>
                    <td rowspan="2" class="bordered">Цена, руб. коп.</td>
                    <td rowspan="2" class="bordered">Сумма буз учета НДС, руб. коп.</td>
                    <td rowspan="2" class="bordered">Порядковый номер по складской картотеке</td>
                </tr>
                <tr>
                    <td class="bordered">счет, субсчет</td>
HERE;
        echo <<<HERE
                    <td class="bordered">наименование</td>
                    <td class="bordered">номенклатрный номер</td>
                    <td class="bordered">код</td>
                    <td class="bordered">наименование</td>
                    <td class="bordered">затребовано</td>
                    <td class="bordered">отпущено</td>
                </tr>
                <tr>
                    <td class="bordered" style="border-bottom-width: 2px; width: 10%;">1</td>
HERE;
        echo <<<HERE
                    <td class="bordered" style="width: 30%;">2</td>
                    <td class="bordered" style="border-bottom-width: 2px;">3</td>
                    <td class="bordered" style="border-bottom-width: 2px; width: 7%;">4</td>
                    <td class="bordered" style="width: 5%;">5</td>
                    <td class="bordered">6</td>
                    <td class="bordered" style="border-bottom-width: 2px;">7</td>
                    <td class="bordered" style="border-bottom-width: 2px;">8</td>
                    <td class="bordered" style="border-bottom-width: 2px;">9</td>
                    <td class="bordered" style="border-bottom-width: 2px;">10</td>
                </tr>
HERE;

        $sum = 0;
        $sums = array(0,0,0,0);
        foreach ($objMaterials as $material) {
            $sum += $material->obj->getTotal();
    
            echo "        <tr>\n";
            echo "            <td class=\"bordered\" style=\"border-left-width: 2px;\">10.1</td>\n";
            echo "            <td class=\"bordered\" style=\"text-align: left;\">".$material->obj->getTitle()."</td>\n";
    
            $mid = $material->obj->getId(); while (strlen($mid) < 6) {$mid = '0'.$mid;}
    
            echo "            <td class=\"bordered\" style=\"border-left-width: 2px;\">".$mid."</td>\n";
    
            $uid = $material->obj->getUnit()->getId(); while (strlen($uid) < 6) {$uid = '0'.$uid;}
    
            echo "            <td class=\"bordered\" style=\"border-right-width: 2px;\">".$uid."</td>\n";
            echo "            <td class=\"bordered\">".$material->obj->getUnit()->getTitle()."</td>\n";
            echo "            <td class=\"bordered\">".number_format($material->count, 3, ".", "")."</td>\n";
            echo "            <td class=\"bordered\" style=\"border-left-width: 2px;\">".number_format($material->count, 3, ".", "")."</td>\n";
            echo "            <td class=\"bordered\">".number_format($material->obj->getPrice(), 2, ".", ",")."</td>\n";
            echo "            <td class=\"bordered\">".number_format($material->obj->getTotal(), 2, ".", ",")."</td>\n";
            echo "            <td class=\"bordered\" style=\"border-right-width: 2px;\"></td>\n";
            echo "        </tr>\n";
            
            $sums[0] += $material->count;
            $sums[1] += $material->count;
            $sums[2] += $material->obj->getPrice();
            $sums[3] += $material->obj->getTotal();
        }
    
        echo "        <tr>\n";
    
        echo "            <td class=\"bordered\" style=\"border-left-width: 2px;\"></td>\n";
        echo "            <td class=\"bordered\" style=\"text-align: left;\"></td>\n";
        echo "            <td class=\"bordered\" style=\"border-left-width: 2px;\"></td>\n";
        echo "            <td class=\"bordered\" style=\"border-right-width: 2px;\"></td>\n";
        
        echo "            <td class=\"bordered\" style=\"border-left-width: 2px; text-align: right;\">Итого</td>\n";
        echo "            <td class=\"bordered\">".number_format($sums[0], 3, ".", "")."</td>\n";
        echo "            <td class=\"bordered\" style=\"border-left-width: 2px;\">".number_format($sums[1], 3, ".", "")."</td>\n";
        echo "            <td class=\"bordered\">".number_format($sums[2], 2, ".", ",")."</td>\n";
        echo "            <td class=\"bordered\">".number_format($sums[3], 2, ".", ",")."</td>\n";
        echo "            <td class=\"bordered\" style=\"border-right-width: 2px;\"></td>\n";
        echo "        </tr>\n";
        
        echo <<<HERE
                <tr>                                                                                                                                                                                                                             
                    <td style="border-top: 2px solid #000; height: 2px;"></td>
HERE;
        echo <<<HERE
                    <td style="height: 2px;"></td>
                    <td style="border-top: 2px solid #000; height: 2px;"></td>
                    <td style="border-top: 2px solid #000; height: 2px;"></td>
                    <td style="height: 2px;"></td>
                    <td style="height: 2px;"></td>
                    <td style="border-top: 2px solid #000; height: 2px;"></td>
                    <td style="border-top: 2px solid #000; height: 2px;"></td>
                    <td style="border-top: 2px solid #000; height: 2px;"></td>
                    <td style="border-top: 2px solid #000; height: 2px;"></td>
                </tr>
                <tr>                                                                                                                                                                                                                             
                    <td colspan="8" style="font-weight: bold; text-align: right;">Всего перемещено на сумму, руб:</td>
HERE;   
        echo "            <td style=\"font-weight: bold;\">".number_format($sum, 2, ".", ",")."</td>\n";
        echo <<<HERE
                    <td></td>
                </tr>
            </table>
        </div>
        <div class="block">
HERE;
        echo '<img src="'.getcwd().'/img/signatures/signature_0.png" alt="" style="height: 75px; position: absolute; margin-left: 135px; margin-bottom: -40px; width: 180px;" />'."\n";
        echo <<<HERE
            <table>
                <tr>
                    <td style="width: 60px; text-align: right;">Отпустил</td>
                    <td style="border-bottom: 1px solid #000; width: 13%;">зав. базой</td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 13%;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 13%;">Ю.Н. Жарков</td>
                    <td></td>
                    <td style="width: 60px; text-align: right;">Получил</td>
                    <td style="border-bottom: 1px solid #000; width: 13%;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 13%;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 13%;"></td>
                </tr>
                <tr>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">должность</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">подпись</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">расшифровка подписи</td>
                    <td></td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">должность</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">подпись</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">расшифровка подписи</td>
                </tr>
            </table>
        </div>
HERE;

        $content = ob_get_contents();
        ob_end_clean();

        $css = file_get_contents('css/pdf.css');

        require_once $this->getParameter('kernel.project_dir').'/../vendor/autoload.php';

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 7,
            'margin_bottom' => 7,
            'margin_header' => 10,
            'margin_footer' => 10,
            'default_font_size' => 8,

            'fontDir' => array_merge($fontDirs, ['fonts']),
            'fontdata' => $fontData + [
                'tahoma' => [
                    'R' => 'tahoma.ttf',
                    'B' => 'tahoma-bold.ttf',
                ],
            ],
            'default_font' => 'tahoma'
        ]);
        $mpdf->debug = true;

        $mpdf->keep_table_proportions = TRUE;
        $mpdf->shrink_tables_to_fit=1;
        $mpdf->SetTitle('Просмотр приходного ордера'.$objStock->getId());
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($content, \Mpdf\HTMLParserMode::HTML_BODY);
        
        $mpdf->Output('Приходный ордер'.$objStock->getId().'.pdf', 'I');
    }

    /**
     * @Route("/stock/print/sm", methods={"GET"})
     * @Security("is_granted('ROLE_STOCK') or is_granted('ROLE_BUH')")
     */
    public function printSM(
        Request $request,
        ProvidersRepository $providersRepository,
        StockRepository $stockRepository,
        StockMaterialsRepository $stockMaterialsRepository,
        StockStockMaterialsRepository $stockStockMaterialsRepository,
        StockApplicationsMaterialsRepository $stockApplicationsMaterialsRepository
    ): Response
    {
        $id = $request->query->get('number');
        if ($id === null || empty($id) || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Проверяем наличие документа
        $objStock = $stockRepository->findBy( array('id' => (int)$id, 'doctype' => 2) );
        if (sizeof($objStock) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($objStock)) {$objStock = array_shift($objStock);}

        //Получаем материалы
        $objSSMaterials = $stockStockMaterialsRepository->findBy( array('stock' => (int)$id) );
        $objMaterials = [];
        foreach ($objSSMaterials as $objSSMaterial) {
            $tmp = new \stdClass;
            $tmp->obj = $objSSMaterial->getStockMaterial();
            $tmp->count = $objSSMaterial->getCount();
            $tmp->id = $objSSMaterial->getId();
            $objMaterials[] = $tmp;
        }

        //Проверяем наличие документа
        $objStockParent = $stockRepository->findBy( array('id' => (int)$objStock->getParent()) );
        if (is_array($objStockParent)) {$objStockParent = array_shift($objStockParent);}

        //Получаем поставщика
        $objProvider = $providersRepository->findBy( array('inn' => (int)$objStockParent->getProvider()) );
        if (sizeof($objProvider) > 0) {
            if (is_array($objProvider)) {$objProvider = array_shift($objProvider);}
        }

        //Печатаем
        ob_start();

        $num = $objStock->getId(); while (strlen($num) < 6) {$num = '0'.$num;}
        echo '<div class="block" style="text-align: right;">Типовая межотраслевая форма № М-11<br />Утверждена постановление Госкомстата России от 30.10.97 № 71а</div>'."\n";
        
        if ($objStockParent->getType() == 3) {
            echo "<div class=\"block\" style=\"text-align: center;\"><h1>АКТ СПИСАНИЯ ПРОДУКТОВ № ".$num."</h1></div>\n";
        } else {
            echo "<div class=\"block\" style=\"text-align: center;\"><h1>АКТ СПИСАНИЯ МАТЕРИАЛОВ № ".$num."</h1></div>\n";
        }
        
        echo <<<HERE
        <div class="block">
            <table>
                <tr>
                    <td colspan="3">&nbsp;</td>
                    <td class="bordered">Коды</td>
                </tr>
                <tr>
                    <td colspan="2">&nbsp;</td>
                    <td style="text-align: right;">Форма по ОКУД</td>
                    <td class="bordered" style="border-left-width: 2px; border-right-width: 2px; border-top-width: 2px;">0315006</td>
                </tr>
                <tr>
                    <td style="text-align: left; width: 160px;">Организация:</td>
                    <td style="border-bottom: 1px solid #000; text-align: left;">ЗАО «Артель старателей «Витим»</td>
                    <td style="text-align: right; width: 100px;">по ОКПО</td>
                    <td class="bordered" style="border-left-width: 2px; border-bottom-width: 2px; border-right-width: 2px; width: 150px;">33288542</td>
                </tr>
                <tr>
                    <td style="text-align: left;">Структурное подразделение:</td>
HERE;
        echo "            <td colspan=\"3\" style=\"border-bottom: 1px solid #000; text-align: left;\">".$objStock->getOffice()->getTitle()."</td>\n";
        echo <<<HERE
                </tr>
            </table>
        </div>
        <div class="block" style="margin-top: 20px;">
            <table>
                <tr>
                    <td class="bordered">Дата составления</td>
                    <td class="bordered">Код вида операции</td>
                    <td class="bordered">Списано со склада</td>
                </tr>
                <tr style="border: 2px solid #000;">
HERE;
        
        echo "            <td class=\"bordered\">".$objStock->getDate()->format('d.m.Y')."</td>\n";
        echo <<<HERE
                    <td class="bordered"></td>
HERE;
        
        echo "            <td class=\"bordered\">".$objStock->getOffice()->getTitle()."</td>\n";
    
        echo <<<HERE
                </tr>
            </table>
        </div>
        <div class="block" style="margin-top: 10px;">
            <table>
                <tr>
                    <td colspan="2" class="bordered">Материальные ценности</td>
                    <td colspan="2" class="bordered">Единица изменения</td>
                    <td class="bordered">Количество</td>
                    <td rowspan="2" class="bordered">Цена, руб. коп.</td>
                    <td rowspan="2" class="bordered">Сумма буз учета НДС, руб. коп.</td>
                    <td rowspan="2" class="bordered">Порядковый номер по складской картотеке</td>
                </tr>
                <tr>
                    <td class="bordered">наименование, сорт, марка, размер</td>
                    <td class="bordered">номенклатурный номер</td>
                    <td class="bordered">код</td>
                    <td class="bordered">наименование</td>
                    <td class="bordered">списано</td>
                </tr>
                <tr>
                    <td class="bordered">1</td>
                    <td class="bordered">2</td>
                    <td class="bordered">3</td>
                    <td class="bordered">4</td>
                    <td class="bordered">5</td>
                    <td class="bordered">6</td>
                    <td class="bordered">7</td>
                    <td class="bordered">8</td>
                </tr>
HERE;
        
        $sum=0;
        foreach ($objMaterials as $material) {
            $sum += $material->obj->getTotal();
    
            echo "        <tr>\n";
            echo "            <td class=\"bordered\" style=\"text-align: left;\">".$material->obj->getTitle()."</td>\n";
    
            $mid = $material->obj->getId(); while (strlen($mid) < 6) {$mid = '0'.$mid;}
    
            echo "            <td class=\"bordered\">".$mid."</td>\n";
    
            $uid = $material->obj->getUnit()->getId(); while (strlen($uid) < 6) {$uid = '0'.$uid;}
    
            echo "            <td class=\"bordered\">".$uid."</td>\n";
            echo "            <td class=\"bordered\">".$material->obj->getUnit()->getTitle()."</td>\n";
            echo "            <td class=\"bordered\">".number_format($material->count, 3, ".", "")."</td>\n";
            echo "            <td class=\"bordered\">".number_format($material->obj->getPrice(), 2, ".", ",")."</td>\n";
            echo "            <td class=\"bordered\">".number_format($material->obj->getTotal(), 2, ".", ",")."</td>\n";
            echo "            <td class=\"bordered\"></td>\n";
            echo "        </tr>\n";
        }
    
        echo <<<HERE
                <tr>                                                                                                                                                                                                                             
                    <td colspan="6" style="font-weight: bold; padding-top: 10px; text-align: right;">Всего по акту списано материалов на сумму, руб:</td>
HERE;
        
        echo "            <td style=\"font-weight: bold; padding-top: 10px;\">".number_format($sum, 2, ".", ",")."</td>\n";
    
        echo <<<HERE
                    <td style="padding-top: 10px;"></td>
                </tr>
            </table>
        </div>
        <div class="block" style="margin-top: 40px; position: relative;">
HERE;
            
        echo '<img src="'.getcwd().'/img/signatures/signature_2.png" alt="" style="height: 75px; position: absolute; margin-left: 440px;  width: 90px;" />'."\n";
        echo '<img src="'.getcwd().'/img/signatures/signature_1.png" alt="" style="height: 75px; position: absolute; margin-left: 370px; margin-top: -30px; width: 90px;" />'."\n";
        echo '<img src="'.getcwd().'/img/signatures/signature_0.png" alt="" style="height: 75px; position: absolute; margin-left: 370px; margin-top: -40px; width: 150px;" />'."\n";
        
        echo <<<HERE
            <table style="margin-top: -150px; position: absolute;">
                <tr>
                    <td style="width: 100px; text-align: right;">Председатель комиссии</td>
                    <td style="border-bottom: 1px solid #000; width: 130px;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 130px;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 130px;"></td>
                </tr>
                <tr>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">должность</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">подпись</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">расшифровка подписи</td>
                </tr>
                <tr>
                    <td style="width: 100px; text-align: right;">Члены комиссии</td>
                    <td style="border-bottom: 1px solid #000; width: 130px;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 130px;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 130px;">В.Н. Жарков</td>
                </tr>
                <tr>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">должность</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">подпись</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">расшифровка подписи</td>
                </tr>
                <tr>
                    <td style="color: #fff; width: 100px; text-align: right;">Члены комиссии</td>
                    <td style="border-bottom: 1px solid #000; width: 130px;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 130px;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 130px;">А.В. Авраменко</td>
                </tr>
                <tr>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">должность</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">подпись</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">расшифровка подписи</td>
                </tr>
                <tr>
                    <td style="width: 100px; text-align: right;">Материально-ответственное лицо</td>
                    <td style="border-bottom: 1px solid #000; width: 130px;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 130px;"></td>
                    <td style="width: 5px;"></td>
                    <td style="border-bottom: 1px solid #000; width: 130px;">Ю.Н. Жарков</td>
                </tr>
                <tr>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">должность</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">подпись</td>
                    <td></td>
                    <td style="font-size: 0.9em; vertical-align: top;">расшифровка подписи</td>
                </tr>
            </table>
        </div>
HERE;        

        $content = ob_get_contents();
        ob_end_clean();

        $css = file_get_contents('css/pdf.css');

        require_once $this->getParameter('kernel.project_dir').'/../vendor/autoload.php';

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 7,
            'margin_bottom' => 7,
            'margin_header' => 10,
            'margin_footer' => 10,
            'default_font_size' => 8,

            'fontDir' => array_merge($fontDirs, ['fonts']),
            'fontdata' => $fontData + [
                'tahoma' => [
                    'R' => 'tahoma.ttf',
                    'B' => 'tahoma-bold.ttf',
                ],
            ],
            'default_font' => 'tahoma'
        ]);
        $mpdf->debug = true;

        $mpdf->keep_table_proportions = TRUE;
        $mpdf->shrink_tables_to_fit=1;
        $mpdf->SetTitle('Просмотр приходного ордера'.$objStock->getId());
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($content, \Mpdf\HTMLParserMode::HTML_BODY);
        
        $mpdf->Output('Приходный ордер'.$objStock->getId().'.pdf', 'I');
    }
}
