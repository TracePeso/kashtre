<?php
namespace App\Support;
/** Product metadata validation. Never prescribing safety or stock qualification. */
final class ItemMedicineMetadata
{
 public static function errors(array $a):array
 {
  if(!in_array($a['is_medicine']??false,[true,1,'1'],true))return in_array($a['strength_not_applicable']??false,[true,1,'1'],true)?['strength_not_applicable'=>'Not applicable strength is only for a marked medicine item.']:[];
  $e=[];
  if(($a['type']??null)!=='good')$e['is_medicine']='Only a goods item can be marked as a medicine.';
  if(trim((string)($a['generic_name']??''))==='')$e['generic_name']='Enter the generic medicine name.';
  $na=in_array($a['strength_not_applicable']??false,[true,1,'1'],true);
  $strength=trim((string)($a['strength']??''));
  if(!$na&&$strength==='')$e['strength']='Enter the medicine strength, including its units, or explicitly select Not applicable.';
  if($na&&$strength!=='')$e['strength']='Clear the strength value before selecting Not applicable.';
  return $e;
 }
}
