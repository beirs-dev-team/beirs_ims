export const formatDateToHuman = (dateIso: string) => {
  if (!dateIso) return

  return Intl.DateTimeFormat('en-US', { month: 'long', day: '2-digit', year: 'numeric' }).format(
    new Date(dateIso)
  )
}

export const formatName = (firstName?: string, middleName?: string, lastName?: string) => {
  return `${firstName ?? ''} ${middleName ?? ''} ${lastName ?? ''} `
}

export const computeAge = (dateIsoStr: string) => {
  const age = Math.floor(
    (Date.now() - new Date(dateIsoStr).getTime()) / (1000 * 60 * 60 * 24 * 365.25)
  )

  if (Number.isNaN(age)) {
    return '-'
  }
  return age.toString()
}

export const dateToday = () => {
  const d = new Date()
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

export const maxDate = () => {
  // Allow selecting today by not subtracting a day
  return dateToday()
}

export const orderedOptions = (optionsList: string[]) => {
  return [...optionsList].sort((a, b) => {
    return a.localeCompare(b)
  })
}
