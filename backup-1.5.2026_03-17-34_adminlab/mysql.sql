-- cPanel mysql backup
GRANT USAGE ON *.* TO 'adminlab'@'103.84.206.119' IDENTIFIED BY PASSWORD '*ED578492BCD026BC69BC2E646F601E59D22E6941';
GRANT ALL PRIVILEGES ON `adminlab\_polkes`.* TO 'adminlab'@'103.84.206.119';
GRANT USAGE ON *.* TO 'adminlab'@'cloud.poltekkesbanten.ac.id' IDENTIFIED BY PASSWORD '*ED578492BCD026BC69BC2E646F601E59D22E6941';
GRANT ALL PRIVILEGES ON `adminlab\_polkes`.* TO 'adminlab'@'cloud.poltekkesbanten.ac.id';
GRANT USAGE ON *.* TO 'adminlab'@'localhost' IDENTIFIED BY PASSWORD '*ED578492BCD026BC69BC2E646F601E59D22E6941';
GRANT ALL PRIVILEGES ON `adminlab\_polkes`.* TO 'adminlab'@'localhost';
