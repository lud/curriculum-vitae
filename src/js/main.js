// Styles
import '../sass/main.scss'

// Icons
import { library, dom } from '@fortawesome/fontawesome-svg-core'
import {
  faLaptopCode,
  faMobileAlt,
  faEnvelope,
  faGlobe,
  faGraduationCap,
  faLayerGroup,
  faDatabase,
  faCode,
  faCubes,
  faComments,
} from '@fortawesome/free-solid-svg-icons'
import {
  faLinkedin
} from '@fortawesome/free-brands-svg-icons'

library.add(
  faLinkedin,
  faLaptopCode,
  faMobileAlt,
  faEnvelope,
  faGlobe, faGraduationCap,
  faLayerGroup,
  faDatabase,
  faCode,
  faCubes,
  faComments
)
dom.i2svg()
