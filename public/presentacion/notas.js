const notas = [
  {
    "voz": "Francisco",
    "idioma": "Inglés",
    "texto": "Good afternoon, professor and general public. Thank you for being here today.\nWe are the Upgrade team. The members of our team are Dominic Hernández, Rodrigo Salas, Manuel Castellanos, and me, Francisco Quezada.\nToday, we will present the project that we developed during this four-month period.\nLet's get started!",
    "indicacion": ""
  },
  {
    "voz": "Francisco",
    "idioma": "Inglés",
    "texto": "We are Upgrade, a student team that develops digital tools for small businesses. Our project is called Barber Agenda.",
    "indicacion": "",
    "traduccion": "Somos Upgrade, un equipo de estudiantes que desarrolla herramientas digitales para pequeños negocios. Nuestro proyecto se llama Barber Agenda."
  },
  {
    "voz": "Dominic",
    "idioma": "Inglés",
    "texto": "Our client is Joss Barber salon, a barbershop and beauty salon in Guadalajara. We spoke with the owner to understand how she manages appointments while taking care of her clients.",
    "indicacion": "",
    "traduccion": "Nuestro cliente es Joss Barber salon, una barbería y estética de Guadalajara. Hablamos con la dueña para entender cómo organiza sus citas mientras atiende a sus clientes."
  },
  {
    "voz": "Francisco",
    "idioma": "Inglés",
    "texto": "Before the project, the owner handled bookings through WhatsApp messages. Imagine she is cutting someone's hair and a client asks for an appointment. She has to stop, check the schedule, and reply. When someone cancels, she also has to find another client for that time slot.",
    "indicacion": "",
    "traduccion": "Antes del proyecto, la dueña agendaba las citas por WhatsApp. Imaginen que está cortando el cabello y alguien le pide una cita. Tiene que parar, revisar la agenda y responder. Si alguien cancela, también tiene que encontrar a otra persona que ocupe ese horario."
  },
  {
    "voz": "Dominic",
    "idioma": "Inglés",
    "texto": "That's why we built Barber Agenda. First, the client chooses a service and an available time on WhatsApp or in the Android app. Then, the system confirms the appointment and adds it to the owner's web panel. The owner can also add bookings directly from the panel.",
    "indicacion": "",
    "traduccion": "Por eso creamos Barber Agenda. Primero, el cliente elige un servicio y un horario disponible por WhatsApp o en la app de Android. Después, el sistema confirma la cita y la agrega al panel web de la dueña. Ella también puede registrar citas directamente desde el panel."
  },
  {
    "voz": "Francisco",
    "idioma": "Inglés",
    "texto": "To sum up, we built a system that makes it easier for clients to book and gives the owner one place to manage appointments. We learned that listening to the client and testing on real phones helped us build a better tool. Our next step is to connect the salon's WhatsApp number and test it with real clients.",
    "indicacion": "Aquí termina la parte en inglés. Pasa a la demostración en español.",
    "traduccion": "En conclusión, creamos un sistema que facilita las reservas y permite a la dueña gestionar sus citas en un solo lugar. Aprendimos que escuchar al cliente y probar la herramienta en teléfonos reales nos ayudó a mejorarla. El siguiente paso es conectar el número de WhatsApp del salón y probarla con clientes reales."
  },
  {
    "voz": "Francisco",
    "idioma": "Español",
    "texto": "En la siguiente pantalla van a ver dos vistas de la misma reserva. A la izquierda está la conversación de la clienta y a la derecha, la agenda de la dueña. Podemos hacer la demostración aquí mismo.",
    "indicacion": ""
  },
  {
    "voz": "Francisco y Dominic",
    "idioma": "Español",
    "texto": "FRANCISCO Voy a elegir un servicio y un horario disponible. Ahora confirmo la reserva desde el chat.\nDOMINIC Miren ese mismo horario en la agenda. Ahora aparece el servicio que Francisco acaba de confirmar.",
    "indicacion": "Seleccionen un servicio y una hora, y pulsen «Confirmar cita». Para repetir el recorrido, usen «Reiniciar demo»."
  },
  {
    "voz": "Francisco",
    "idioma": "Español",
    "texto": "En el panel, la dueña puede revisar el día por áreas, por ejemplo, cortes y color. Si alguien llama o llega al salón, también puede registrar la cita desde aquí. Al terminar, puede marcar si la persona fue atendida o si no asistió. La agenda también maneja recordatorios.",
    "indicacion": "Señala las columnas por área en la captura del panel."
  },
  {
    "voz": "Dominic",
    "idioma": "Español",
    "texto": "Si una clienta prefiere usar Android, puede entrar con un código que le llega por WhatsApp. Desde la app puede agendar, consultar sus próximas citas, revisar su tarjeta de sellos y ver cómo llegar al salón. La cita aparece en el mismo panel que acabamos de mostrar.",
    "indicacion": "Pulsa «Agendar», «Mis citas» y «Tarjeta» para mostrar cada pantalla."
  },
  {
    "voz": "Dominic y Francisco",
    "idioma": "Español",
    "texto": "DOMINIC Si alguien cancela, la dueña puede volver a ofrecer ese horario a quienes aceptaron los avisos.\nFRANCISCO La primera persona que responde «AGENDAR» obtiene el espacio. El sistema informa a quienes respondan después que la cita ya está ocupada.",
    "indicacion": ""
  },
  {
    "voz": "Francisco",
    "idioma": "Español",
    "texto": "La clienta puede ver cuántos sellos lleva en su tarjeta. El ejemplo que configuramos da un sello por visita y un corte gratis al completar diez. También se puede enviar una invitación para volver a reservar y la dueña decide cuánto destinar a los mensajes promocionales.",
    "indicacion": ""
  },
  {
    "voz": "Dominic",
    "idioma": "Español",
    "texto": "Aquí la dueña puede consultar qué servicios se piden más y cuáles son las horas con más citas. Puede revisar un día, una semana o un mes. Si necesita compartir el reporte, lo descarga en PDF o Excel desde el panel.",
    "indicacion": "Muestra el tablero y explica qué periodo se puede consultar en el sistema."
  },
  {
    "voz": "Dominic",
    "idioma": "Español",
    "texto": "La reserva por WhatsApp puede funcionar aunque el salón esté cerrado. Usamos la plataforma oficial de WhatsApp Business. La dueña abre su panel en el navegador y las clientas pueden usar el chat para reservar. Antes de actualizar el sistema revisamos los flujos con pruebas automáticas.",
    "indicacion": ""
  },
  {
    "voz": "Francisco",
    "idioma": "Español",
    "texto": "Primero se cargan los horarios y servicios del salón. Después se conecta el número del negocio y se le muestra a la dueña cómo usar el panel. Por ahora la demostración usa un número de prueba. El siguiente paso es hacer una prueba piloto con el número del salón.",
    "indicacion": ""
  },
  {
    "voz": "Dominic",
    "idioma": "Español",
    "texto": "Entregamos un sistema que reúne las reservas hechas desde WhatsApp, la app y el panel. En la demostración vieron cómo se agrega una cita y cómo se vuelve a ofrecer un horario que se liberó. La versión de prueba se construyó con herramientas gratuitas y un dominio que ya teníamos.",
    "indicacion": ""
  },
  {
    "voz": "Francisco y Dominic",
    "idioma": "Español",
    "texto": "Muchas gracias por escucharnos. Si quieren ver alguna pantalla de nuevo o tienen preguntas sobre el sistema, con gusto las respondemos.",
    "indicacion": ""
  }
];
