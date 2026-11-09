<?php
declare(strict_types=1);

function visit_stores(PDO $db, array $input, string $fallback): array
{
    if (!array_key_exists('destinations', $input)) return [];
    $names = $input['destinations'];
    if (!is_array($names) || count($names) < 1 || count($names) > 20) throw new InvalidArgumentException('Select between 1 and 20 stores.');
    $stores = [];
    $q = $db->prepare('SELECT id, name FROM destinations WHERE name = ? AND is_active = 1');
    foreach ($names as $name) {
        if (!is_string($name)) throw new InvalidArgumentException('Invalid store selection.');
        $q->execute([$name]); $store = $q->fetch();
        if (!$store) throw new InvalidArgumentException('A selected store is unavailable. Refresh the store list.');
        if (mb_strlen($store['name']) > 100) throw new InvalidArgumentException('Store name exceeds the parking record limit. Shorten it in the store directory.');
        $stores[$store['id']] = $store;
    }
    return array_values($stores);
}
function visit_store_save(PDO $db, string $type, int $id, array $stores): void
{
    $db->prepare('DELETE FROM visit_destinations WHERE entity_type=? AND entity_id=?')->execute([$type,$id]);
    $q=$db->prepare('INSERT INTO visit_destinations(entity_type,entity_id,destination_id,destination_name,position) VALUES(?,?,?,?,?)');
    foreach ($stores as $position=>$store) $q->execute([$type,$id,$store['id'],$store['name'],$position]);
}
function visit_store_rows(PDO $db, string $type, int $id): array
{
    $q=$db->prepare('SELECT destination_id id, destination_name name FROM visit_destinations WHERE entity_type=? AND entity_id=? ORDER BY position');
    $q->execute([$type,$id]); return $q->fetchAll();
}
function visit_store_decorate(PDO $db, array $rows, string $type='session'): array
{
    if (!$rows) return $rows;
    // One bounded lookup per page/roster, not a query per vehicle.
    $ids=array_column($rows,'id');
    $q=$db->prepare('SELECT entity_id,destination_name FROM visit_destinations WHERE entity_type=? AND entity_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY position');
    $q->execute(array_merge([$type],$ids)); $map=[];
    foreach($q->fetchAll() as $r) $map[$r['entity_id']][]=$r['destination_name'];
    foreach($rows as &$r) { $r['destinations']=$map[$r['id']]??[$r['destination']]; $r['destination']=implode(', ',$r['destinations']); }
    return $rows;
}
