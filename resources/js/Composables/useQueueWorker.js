import { ref } from 'vue'

const isWorkerRunning = ref(true)

export function useQueueWorker() {
  const setWorkerRunning = (running) => {
    isWorkerRunning.value = Boolean(running)
  }

  return {
    isWorkerRunning,
    setWorkerRunning
  }
}
