function classExtend(subclass, superclass) {
  function Dummy(){}
  Dummy.prototype = superclass.prototype;

  subclass.prototype = new Dummy();
  subclass.prototype.constructor = subclass.constructor;
  subclass.superclass = superclass;
  subclass.superproto = superclass.prototype;
}
