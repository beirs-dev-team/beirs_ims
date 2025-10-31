import AppLayout from '@/components/layout/AppLayout.vue'
import AboutPage from '@/views/LandingPage/AboutPage/AboutPage.vue'

import LandingPage from '@/views/LandingPage/LandingPage.vue'
import LoginPage from '@/views/LandingPage/LoginPage/LoginPage.vue'
import RegistrationPage from '@/views/LandingPage/RegisterPage/RegistrationPage.vue'
import ServicesPage from '@/views/LandingPage/ServicesPage/ServicesPage.vue'
import DashBoardPage from '@/views/ResidentPage/DashBoardPage.vue'

import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: AppLayout,
    children: [
      {
        path: '/',
        name: 'LandingPage',
        component: LandingPage,
        meta: {
          title: 'Home',
        },
      },
      {
        path: '/login',
        name: 'LoginPage',
        component: LoginPage,
        meta: {
          title: 'Login',
        },
      },
      {
        path: '/registration',
        name: 'RegisterPage',
        component: RegistrationPage,
        meta: {
          title: 'Registration',
        },
      },
      {
        path: '/about',
        name: 'AboutPage',
        component: AboutPage,
        meta: {
          title: 'About Us',
        },
      },
      {
        path: '/services',
        name: 'ServicesPage',
        component: ServicesPage,
        meta: {
          title: 'Services',
        },
      },

      {
        path: '/dashboard',
        name: 'DashboardPage',
        component: DashBoardPage,
        meta: {
          title: 'Dashboard',
        }
      }
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(_to, _from, savedPosition) {
    // if navigating back/forward in browser history
    if (savedPosition) {
      return savedPosition
    } else {
      // always scroll to top
      return { left: 0, top: 0 }
    }
  },
})

router.beforeEach(async (to, _from, next) => {
  // タイトルの設定
  const title = to.meta.title
  if (typeof title === 'string' && title) {
    document.title = `${title} - BEIRS-IMS`
  }

  next()
})

export default router
