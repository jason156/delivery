CREATE TABLE IF NOT EXISTS pin_storage (
  id int(11) AUTO_INCREMENT,
  ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  service varchar(150) NULL DEFAULT '',
  auth text NOT NULL DEFAULT '',
  PRIMARY KEY (id)
)
