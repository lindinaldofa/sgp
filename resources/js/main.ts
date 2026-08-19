import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import DialogService from 'primevue/dialogservice'
import 'primeicons/primeicons.css'

// Componentes PrimeVue
import Tooltip from 'primevue/tooltip'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Checkbox from 'primevue/checkbox'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import Textarea from 'primevue/textarea'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Paginator from 'primevue/paginator'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import ProgressBar from 'primevue/progressbar'
import ProgressSpinner from 'primevue/progressspinner'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import Skeleton from 'primevue/skeleton'
import Badge from 'primevue/badge'
import Chip from 'primevue/chip'
import Divider from 'primevue/divider'
import Card from 'primevue/card'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import FileUpload from 'primevue/fileupload'
import Message from 'primevue/message'
import RadioButton from 'primevue/radiobutton'
import Editor from 'primevue/editor'

import App from './App.vue'
import router from './router'
import axios from './utils/axios'

const app = createApp(App)

// Pinia (state management)
app.use(createPinia())

// Vue Router
app.use(router)

// PrimeVue com tema Aura
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      prefix: 'p',
      darkModeSelector: '.dark',
      cssLayer: false,
    },
  },
  locale: {
    accept: 'Sim',
    reject: 'Não',
    choose: 'Escolher',
    upload: 'Enviar',
    cancel: 'Cancelar',
    close: 'Fechar',
    noFileSelected: 'Nenhum arquivo selecionado',
    searchMessage: '{0} resultados disponíveis',
    selectionMessage: '{0} itens selecionados',
    emptySearchMessage: 'Nenhum resultado encontrado',
    emptyMessage: 'Nenhum dado encontrado',
    fileSizeTypes: ['B', 'KB', 'MB', 'GB', 'TB'],
    dayNames: ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'],
    dayNamesShort: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
    dayNamesMin: ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'],
    monthNames: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
    monthNamesShort: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
    today: 'Hoje',
    weekHeader: 'Sem',
    firstDayOfWeek: 0,
    dateFormat: 'dd/mm/yy',
    weak: 'Fraco',
    medium: 'Médio',
    strong: 'Forte',
    passwordPrompt: 'Digite uma senha',
    emptyFilterMessage: 'Nenhum resultado',
    pageReportTemplate: '{first} a {last} de {totalRecords}',
    firstPageLabel: 'Primeira',
    lastPageLabel: 'Última',
    nextPageLabel: 'Próxima',
    prevPageLabel: 'Anterior',
    rowsPerPageLabel: 'Linhas por página',
    jumpToPageDropdownLabel: 'Ir para página',
    jumpToPageInputLabel: 'Ir para página',
    sortAscending: 'Crescente',
    sortDescending: 'Decrescente',
    apply: 'Aplicar',
    clear: 'Limpar',
    matchAll: 'Todos',
    matchAny: 'Algum',
    addRule: 'Adicionar regra',
    removeRule: 'Remover regra',
    equals: 'Igual',
    notEquals: 'Diferente',
    lt: 'Menor que',
    lte: 'Menor ou igual',
    gt: 'Maior que',
    gte: 'Maior ou igual',
    contains: 'Contém',
    notContains: 'Não contém',
    startsWith: 'Começa com',
    endsWith: 'Termina com',
    between: 'Entre',
  },
})

// Serviços e diretivas PrimeVue
app.directive('tooltip', Tooltip)
app.use(ToastService)
app.use(ConfirmationService)
app.use(DialogService)

// Registro global de componentes PrimeVue
app.component('Button', Button)
app.component('InputText', InputText)
app.component('Password', Password)
app.component('Checkbox', Checkbox)
app.component('Select', Select)
app.component('MultiSelect', MultiSelect)
app.component('Textarea', Textarea)
app.component('DataTable', DataTable)
app.component('Column', Column)
app.component('Paginator', Paginator)
app.component('Tag', Tag)
app.component('Toast', Toast)
app.component('ConfirmDialog', ConfirmDialog)
app.component('Dialog', Dialog)
app.component('ProgressBar', ProgressBar)
app.component('ProgressSpinner', ProgressSpinner)
app.component('IconField', IconField)
app.component('InputIcon', InputIcon)
app.component('DatePicker', DatePicker)
app.component('InputNumber', InputNumber)
app.component('Skeleton', Skeleton)
app.component('Badge', Badge)
app.component('Chip', Chip)
app.component('Divider', Divider)
app.component('Card', Card)
app.component('Tabs', Tabs)
app.component('TabList', TabList)
app.component('Tab', Tab)
app.component('TabPanels', TabPanels)
app.component('TabPanel', TabPanel)
app.component('FileUpload', FileUpload)
app.component('Message', Message)
app.component('RadioButton', RadioButton)
app.component('Editor', Editor)

// Disponibilizar axios globalmente (opcional — preferir usar via composable)
app.config.globalProperties.$axios = axios

app.mount('#app')
