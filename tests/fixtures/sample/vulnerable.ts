import * as models from "./models";
import * as libxml from "libxmljs2";
const key = "-----BEGIN RSA PRIVATE KEY-----\nMIIE...";
export function login(req: any, res: any) {
  models.sequelize.query("SELECT * FROM Users WHERE email = '" + req.body.email + "'");
  models.sequelize.query(`SELECT * FROM Users WHERE email = '${req.body.email}'`, { plain: true });
  eval(req.body.code);
  libxml.parseXml(req.body.xml, { noblanks: true, noent: true, nocdata: true });
  res.redirect(req.query.to);
}
