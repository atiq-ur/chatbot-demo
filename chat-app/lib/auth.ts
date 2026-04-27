export function getToken() {
  if (typeof window === 'undefined') return null
  return localStorage.getItem('nexus_token')
}

export function setToken(token: string) {
  if (typeof window !== 'undefined') {
    localStorage.setItem('nexus_token', token)
  }
}

export function clearToken() {
  if (typeof window !== 'undefined') {
    localStorage.removeItem('nexus_token')
  }
}

export function getUser() {
  if (typeof window === 'undefined') return null
  const user = localStorage.getItem('nexus_user')
  return user ? JSON.parse(user) : null
}

export function setUser(user: any) {
  if (typeof window !== 'undefined') {
    localStorage.setItem('nexus_user', JSON.stringify(user))
  }
}

export async function apiFetch(url: string, options: RequestInit = {}) {
  const token = getToken()
  const headers = {
    ...options.headers,
    'Accept': 'application/json',
  } as any

  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  // Only set Content-Type if it's not FormData
  if (!(options.body instanceof FormData)) {
      if (!headers['Content-Type']) {
          headers['Content-Type'] = 'application/json'
      }
  }

  const res = await fetch(url, { ...options, headers })
  
  if (res.status === 401) {
    clearToken()
    if (typeof window !== 'undefined' && !window.location.pathname.includes('/login') && !window.location.pathname.includes('/register')) {
      window.location.href = '/login'
    }
  }
  
  return res
}
