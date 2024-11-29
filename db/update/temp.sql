SET NAMES 'utf8';

ALTER TABLE promocodes ADD enddate  DATE DEFAULT null; 
ALTER TABLE note_topics ADD ispublic   tinyint(1) DEFAULT 0;
ALTER TABLE note_topicnode ADD islink  tinyint(1) DEFAULT 0;
 

DROP VIEW IF EXISTS item_cat_view  ;

CREATE VIEW item_cat_view
AS
SELECT
  ic.cat_id AS cat_id,
  ic.cat_name AS cat_name,
  ic.detail AS detail,
  ic.parent_id AS parent_id,
  COALESCE((SELECT
      COUNT(*)
    FROM items i
    WHERE i.cat_id = ic.cat_id), 0) AS itemscnt  ,
    COALESCE((SELECT
      COUNT(*)
    FROM item_cat ic2
    WHERE ic.cat_id = ic2.parent_id), 0) AS childcnt
FROM item_cat ic   ;


DROP VIEW IF EXISTS custitems_view  ;
DROP TABLE IF EXISTS custitems  ;

CREATE TABLE custitems (
  custitem_id int(11) NOT NULL AUTO_INCREMENT,
  item_id int(11)   NULL,
  customer_id int(11) NOT NULL,
  quantity decimal(10, 3) DEFAULT NULL,
  price decimal(10, 2) NOT NULL DEFAULT '0.00',
  cust_code varchar(255) NOT NULL,
  cust_name varchar(255) NOT NULL,
  brand varchar(255) NOT NULL,
  details TEXT DEFAULT NULL,
  updatedon date NOT NULL,
  PRIMARY KEY (custitem_id),
  KEY item_id (item_id)
) ENGINE = INNODB  DEFAULT CHARSET = utf8;


CREATE
VIEW custitems_view
AS
SELECT
  s.custitem_id AS custitem_id,
  s.cust_name AS cust_name,
  s.item_id AS item_id,
  s.customer_id AS customer_id,
  s.quantity AS quantity,
  s.price AS price,
  s.cust_code AS cust_code,
  s.brand AS brand,
  s.details AS details,
  s.updatedon AS updatedon,
  i.itemname AS itemname,
  i.item_code AS item_code,
  i.cat_id AS cat_id,
  c.customer_name AS customer_name
FROM ((custitems s
  LEFT JOIN items i
    ON ((s.item_id = i.item_id)))
  JOIN customers c
    ON ((s.customer_id = c.customer_id)))
 ;

 
delete  from  options where  optname='version' ;
insert  into options (optname,optvalue) values('version','6.12.0'); 

 