import * as models from "./models";
import * as libxml from "libxmljs2";
export function login(req: any, res: any) {
  models.sequelize.query("SELECT * FROM Users WHERE email = :email", { replacements: { email: req.body.email } });
  libxml.parseXml(req.body.xml, { noblanks: true, noent: false });
  eval("1 + 1");
  res.redirect("/home");
}
