<?php
require dirname(__DIR__).'/app/Support/ItemMedicineMetadata.php';
use App\Support\ItemMedicineMetadata;
$n=0;function check(bool $b){global $n;$n++;if(!$b)throw new RuntimeException('assertion '.$n);}
check(ItemMedicineMetadata::errors(['type'=>'service'])===[]);
check(ItemMedicineMetadata::errors(['type'=>'good','name'=>'Retail item'])===[]);
$medicine=['type'=>'good','is_medicine'=>true,'generic_name'=>'Synthetic generic','strength'=>'500 mg'];
check(ItemMedicineMetadata::errors($medicine)===[]);
check(isset(ItemMedicineMetadata::errors(array_replace($medicine,['strength'=>'']))['strength']));
check(isset(ItemMedicineMetadata::errors(array_replace($medicine,['generic_name'=>'']))['generic_name']));
check(isset(ItemMedicineMetadata::errors(array_replace($medicine,['type'=>'service']))['is_medicine']));
check(ItemMedicineMetadata::errors(array_replace($medicine,['strength'=>'','strength_not_applicable'=>true]))===[]);
check(isset(ItemMedicineMetadata::errors(array_replace($medicine,['strength_not_applicable'=>true]))['strength']));
echo "PASS: $n Main metadata assertions\n";
