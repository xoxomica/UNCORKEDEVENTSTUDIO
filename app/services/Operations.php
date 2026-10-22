<?php
declare(strict_types=1);
function stock(int $id): int { return (int)run('SELECT COALESCE(SUM(quantity),0) FROM movements WHERE product_id=?',[$id])->fetchColumn(); }
function active_record(string $table,int $id): array { if(!in_array($table,['products','suppliers','employees','packages','venues'],true))throw new LogicException('Invalid resource');$row=run("SELECT * FROM $table WHERE id=? AND active=1",[$id])->fetch();if(!$row)throw new RuntimeException('The selected record is unavailable.');return $row; }
function change_status(int $id,string $status): void {
 if(!in_array($status,['Pending','Confirmed','Cancelled','Completed','Archived'],true))throw new RuntimeException('Invalid status.');
 transaction(function()use($id,$status){$r=run('SELECT * FROM reservations WHERE id=?',[$id])->fetch();if(!$r)throw new RuntimeException('Reservation not found.');
 if(in_array($status,['Pending','Confirmed','Completed'],true)){
  $conflict=run("SELECT COUNT(*) FROM assignments a JOIN assignments b ON b.employee_id=a.employee_id AND b.reservation_id<>a.reservation_id JOIN reservations other ON other.id=b.reservation_id WHERE a.reservation_id=? AND other.status NOT IN ('Cancelled','Archived') AND a.start<b.end AND a.end>b.start",[$id])->fetchColumn();
  if($conflict)throw new RuntimeException('Employee already has an overlapping assignment. Remove or change the assignment before reactivating this event.');
 }
 if(in_array($status,['Confirmed','Completed'],true)){
  if(!$r['venue_id'])throw new RuntimeException('Assign a venue before confirming.');$venue=active_record('venues',(int)$r['venue_id']);if($r['guests']>$venue['capacity'])throw new RuntimeException('Guest count exceeds venue capacity.');
  $count=run("SELECT COUNT(*) FROM reservations WHERE id<>? AND venue_id=? AND date=? AND status IN ('Confirmed','Completed') AND start<? AND end>?",[$id,$r['venue_id'],$r['date'],$r['end'],$r['start']])->fetchColumn();if($count)throw new RuntimeException('This venue already has an overlapping confirmed event.');
 }
 run('UPDATE reservations SET status=? WHERE id=?',[$status,$id]);audit('Reservation status: '.$status,$id);
 });
}
function receive_purchase(int $supplier,int $product,int $quantity,int $cost,string $reference,int $actor): void {
 transaction(function()use($supplier,$product,$quantity,$cost,$reference,$actor){active_record('suppliers',$supplier);active_record('products',$product);if($quantity<1||$cost<0)throw new RuntimeException('Invalid purchase values.');run('INSERT INTO purchases(supplier_id,product_id,quantity,unit_cost,reference,user_id,created_at) VALUES(?,?,?,?,?,?,?)',[$supplier,$product,$quantity,$cost,$reference,$actor,date('c')]);run('INSERT INTO movements(product_id,quantity,reason,user_id,created_at) VALUES(?,?,?,?,?)',[$product,$quantity,'Purchase received: '.$reference,$actor,date('c')]);audit('Purchase received: '.$reference);});
}
function sell(int $product,int $quantity,?int $reservation,string $status,int $actor): void {
 transaction(function()use($product,$quantity,$reservation,$status,$actor){$p=active_record('products',$product);if($quantity<1||stock($product)<$quantity)throw new RuntimeException('Insufficient stock for this sale.');if(!in_array($status,['Paid','Unpaid'],true))throw new RuntimeException('Invalid payment status.');if($reservation&&!run('SELECT id FROM reservations WHERE id=?',[$reservation])->fetch())throw new RuntimeException('Reservation not found.');$reference='SALE-'.strtoupper(bin2hex(random_bytes(4)));$now=date('c');run('INSERT INTO sales(reference,reservation_id,product_id,quantity,unit_price,status,user_id,created_at,paid_at) VALUES(?,?,?,?,?,?,?,?,?)',[$reference,$reservation,$product,$quantity,$p['price'],$status,$actor,$now,$status==='Paid'?$now:null]);run('INSERT INTO movements(product_id,quantity,reason,user_id,created_at) VALUES(?,?,?,?,?)',[$product,-$quantity,'Sale: '.$reference,$actor,date('c')]);audit('Sale recorded: '.$reference);});
}
function record_payment(int $id,int $amount,string $method,string $reference,int $actor): void {
 transaction(function()use($id,$amount,$method,$reference,$actor){$r=run('SELECT * FROM reservations WHERE id=?',[$id])->fetch();if(!$r||in_array($r['status'],['Cancelled','Archived'],true))throw new RuntimeException('This reservation cannot receive payments.');$paid=(int)run('SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id=?',[$id])->fetchColumn();if($amount<=0||$paid+$amount>$r['total'])throw new RuntimeException('Payment exceeds the remaining event balance. Check the agreed event total.');if(!in_array($method,['Cash','Bank transfer','E-wallet'],true))throw new RuntimeException('Invalid payment method.');run('INSERT INTO payments(reservation_id,amount,method,reference,user_id,created_at) VALUES(?,?,?,?,?,?)',[$id,$amount,$method,$reference,$actor,date('c')]);audit('Payment recorded: '.money($amount),$id);});
}

function settle_sale(int $id): void {
 transaction(function()use($id){
  $sale=run('SELECT status FROM sales WHERE id=?',[$id])->fetch();
  if(!$sale)throw new RuntimeException('Sale not found.');
  if($sale['status']==='Paid')return;
  run("UPDATE sales SET status='Paid',paid_at=? WHERE id=? AND status='Unpaid'",[date('c'),$id]);
  audit('Sale marked paid: '.$id);
 });
}
