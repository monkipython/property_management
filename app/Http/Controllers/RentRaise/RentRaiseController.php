-- 11/20/06
-- 10/17/14 <v2> look at the X
-- 12/17/14 <v3> fix <v2>
-- 12/19/14 <v4> p.prop_class <> 'X'
drop table if exists rent_raise@2@
go
CREATE TABLE
Rent_raise@2@ (
raise decimal(12,2),
Notice Char(2),
raise_60 decimal(12,2),
move_in_date Date,
name Char(40),
Type Char(2),
Bed_room Char(2),
bath_room Char(2),
Prop Char(6),
unit Char(8),
tenant smallint unsigned,
rent decimal(12,2),
rent_old decimal(12,2),
diff decimal(12,2),
last_date Date,
raise_pct decimal(12,6),
raise_pct2 decimal(12,6),
Address Char(36),
City Char(20),
Group1 Char(6)
)
 engine = myisam
go
CREATE Unique INDEX Rent_raise@2@_k1 ON Rent_raise@2@
(prop,unit,tenant)
go
-- 
INSERT INTO Rent_raise@2@ (raise,Notice,raise_60,move_in_date,name
,Type,Bed_room,bath_room
,prop,unit,tenant
,rent,rent_old,diff,last_date,Raise_pct,Raise_pct2
,Address,City,Group1)
SELECT 0,'30',0,t.move_in_date,tnt_name
,u.unit_Type,u.Bedrooms,u.bathrooms
,t.prop,t.unit,t.tenant,base_rent,0,0
-- ,lease_esc_date
,Lease_start_date
,p.Raise_pct,p.Raise_pct2
,p.street,p.City,p.Group1
FROM tenant t
INNER join prop_@0@ up on up.prop = t.prop
left outer join prop p on t.prop = p.prop
left outer join unit u on t.prop = u.prop and t.unit = u.unit
where t.move_in_date <= '@4@'
and t.prop = up.prop
and t.move_out_date > '@4@'
-- and lease_esc_date between '@5@' and '@6@'
and lease_start_date between '@5@' and '@6@'
and t.unit <>'ZZZZ'
and base_rent > 0
-- and t.prop between '@1@' and '@2@'
and p.group1 ='@1@'
-- <v4>
and p.prop_class <> 'X'
go
UPDATE
Rent_raise@2@ t1 
INNER JOIN rate_hist t ON t1.prop = t.prop
and t1.unit = t.unit and t1.tenant = t.tenant and t.tx_type ='MI'
-- <v3>
INNER JOIN prop p on t1.prop = p.prop
SET t1.rent_old = t.Base_rent
WHERE t.tx_date < '@4@'
and t.stop_date > '@3@'
and t.stop_date < '8888-12-31'
-- <v2>
and p.prop_class <> 'X'
go
-- 
UPDATE
Rent_raise@2@ t1 INNER JOIN rate_hist t ON t1.prop = t.prop
and t1.unit = t.unit and t1.tenant = t.tenant and t.tx_type ='RC'
SET t1.rent_old = t.Base_rent
WHERE t.tx_date < '@4@'
and t.stop_date > '@3@'
and t.stop_date < '8888-12-31'
goend
